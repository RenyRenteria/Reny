<?php

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class ReportPeriod
{
    public const PRESETS = ['7d', '30d', '90d', '12m', 'custom'];

    public function __construct(
        public string $preset,
        public string $timezone,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $previousStart,
        public CarbonImmutable $previousEnd,
    ) {}

    public static function fromRequest(Request $request, ?CarbonImmutable $now = null): self
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', Rule::in(self::PRESETS)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $timezone = (string) config('admin.publishing_timezone', config('app.timezone', 'UTC'));
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $preset = (string) ($validated['period'] ?? '30d');

        if ($preset === 'custom') {
            $start = CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['from'], $timezone)->startOfDay();
            $end = CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['to'], $timezone)->endOfDay();
        } else {
            $end = $now->endOfDay();
            $start = match ($preset) {
                '7d' => $end->startOfDay()->subDays(6),
                '90d' => $end->startOfDay()->subDays(89),
                '12m' => $end->startOfMonth()->subMonthsNoOverflow(11),
                default => $end->startOfDay()->subDays(29),
            };
        }

        $durationMicroseconds = $start->diffInMicroseconds($end);
        $previousEnd = $start->subMicrosecond();
        $previousStart = $previousEnd->subMicroseconds($durationMicroseconds);

        return new self(
            preset: $preset,
            timezone: $timezone,
            start: $start,
            end: $end,
            previousStart: $previousStart,
            previousEnd: $previousEnd,
        );
    }

    public function isDaily(): bool
    {
        return $this->start->diffInDays($this->end) <= 90;
    }

    /** @return array<string, string> */
    public function query(): array
    {
        $query = ['period' => $this->preset];

        if ($this->preset === 'custom') {
            $query['from'] = $this->start->toDateString();
            $query['to'] = $this->end->toDateString();
        }

        return $query;
    }

    public function label(): string
    {
        return $this->start->format('M j, Y').' – '.$this->end->format('M j, Y');
    }

    public function previousLabel(): string
    {
        return $this->previousStart->format('M j, Y').' – '.$this->previousEnd->format('M j, Y');
    }
}
