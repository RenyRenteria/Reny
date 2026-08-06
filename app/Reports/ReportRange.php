<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ReportRange
{
    public const PRESETS = ['7d', '30d', '90d', '12m', 'custom'];

    private function __construct(
        public readonly string $preset,
        public readonly string $timezone,
        public readonly CarbonImmutable $startLocal,
        public readonly CarbonImmutable $endExclusiveLocal,
        public readonly CarbonImmutable $previousStartLocal,
        public readonly CarbonImmutable $previousEndExclusiveLocal,
    ) {}

    public static function fromRequest(Request $request, ?CarbonImmutable $now = null): self
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'string', Rule::in(self::PRESETS)],
            'start' => ['nullable', 'required_if:preset,custom', 'date_format:Y-m-d'],
            'end' => ['nullable', 'required_if:preset,custom', 'date_format:Y-m-d', 'after_or_equal:start'],
            'product_sort' => ['nullable', 'string', Rule::in(['sales', 'units'])],
        ]);

        $timezone = (string) config('admin.publishing_timezone', 'America/Panama');
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $preset = (string) ($validated['preset'] ?? '30d');
        $today = $now->startOfDay();

        if ($preset === 'custom') {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', (string) $validated['start'], $timezone)->startOfDay();
            $endExclusive = CarbonImmutable::createFromFormat('!Y-m-d', (string) $validated['end'], $timezone)
                ->startOfDay()
                ->addDay();
        } else {
            $days = match ($preset) {
                '7d' => 7,
                '90d' => 90,
                '12m' => $today->subYear()->addDay()->diffInDays($today->addDay()),
                default => 30,
            };
            $start = $today->subDays($days - 1);
            $endExclusive = $today->addDay();
        }

        $durationDays = max(1, (int) $start->diffInDays($endExclusive));
        $previousEnd = $start;
        $previousStart = $previousEnd->subDays($durationDays);

        return new self(
            preset: $preset,
            timezone: $timezone,
            startLocal: $start,
            endExclusiveLocal: $endExclusive,
            previousStartLocal: $previousStart,
            previousEndExclusiveLocal: $previousEnd,
        );
    }

    public function startUtc(): CarbonImmutable
    {
        return $this->startLocal->utc();
    }

    public function endExclusiveUtc(): CarbonImmutable
    {
        return $this->endExclusiveLocal->utc();
    }

    public function previousStartUtc(): CarbonImmutable
    {
        return $this->previousStartLocal->utc();
    }

    public function previousEndExclusiveUtc(): CarbonImmutable
    {
        return $this->previousEndExclusiveLocal->utc();
    }

    public function days(): int
    {
        return max(1, (int) $this->startLocal->diffInDays($this->endExclusiveLocal));
    }

    public function granularity(): string
    {
        return $this->days() <= 90 ? 'day' : 'month';
    }

    public function startDate(): string
    {
        return $this->startLocal->toDateString();
    }

    public function endDate(): string
    {
        return $this->endExclusiveLocal->subDay()->toDateString();
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        if ($this->preset !== 'custom') {
            return ['preset' => $this->preset];
        }

        return [
            'preset' => 'custom',
            'start' => $this->startDate(),
            'end' => $this->endDate(),
        ];
    }

    public function filenameSuffix(): string
    {
        return $this->startDate().'_'.$this->endDate();
    }
}
