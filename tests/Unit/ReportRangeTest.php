<?php

namespace Tests\Unit;

use App\Reports\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportRangeTest extends TestCase
{
    public function test_seven_day_preset_uses_local_calendar_boundaries_and_equal_previous_period(): void
    {
        config(['admin.publishing_timezone' => 'America/Panama']);
        $request = Request::create('/reports', 'GET', ['preset' => '7d']);
        $now = CarbonImmutable::parse('2026-08-06 07:44:00', 'America/Panama');

        $range = ReportRange::fromRequest($request, $now);

        $this->assertSame('2026-07-31', $range->startDate());
        $this->assertSame('2026-08-06', $range->endDate());
        $this->assertSame('2026-07-24', $range->previousStartLocal->toDateString());
        $this->assertSame('2026-07-31', $range->previousEndExclusiveLocal->toDateString());
        $this->assertSame('2026-07-31 05:00:00', $range->startUtc()->format('Y-m-d H:i:s'));
        $this->assertSame(7, $range->days());
        $this->assertSame('day', $range->granularity());
    }

    public function test_custom_range_is_inclusive_in_ui_and_exclusive_in_queries(): void
    {
        config(['admin.publishing_timezone' => 'America/Panama']);
        $request = Request::create('/reports', 'GET', [
            'preset' => 'custom',
            'start' => '2026-08-01',
            'end' => '2026-08-03',
        ]);

        $range = ReportRange::fromRequest($request);

        $this->assertSame('2026-08-01', $range->startDate());
        $this->assertSame('2026-08-03', $range->endDate());
        $this->assertSame('2026-08-04 05:00:00', $range->endExclusiveUtc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-29', $range->previousStartLocal->toDateString());
        $this->assertSame('2026-08-01', $range->previousEndExclusiveLocal->toDateString());
        $this->assertSame([
            'preset' => 'custom',
            'start' => '2026-08-01',
            'end' => '2026-08-03',
        ], $range->query());
    }

    public function test_long_ranges_use_monthly_granularity(): void
    {
        $request = Request::create('/reports', 'GET', ['preset' => '12m']);
        $now = CarbonImmutable::parse('2026-08-06 07:44:00', 'America/Panama');

        $range = ReportRange::fromRequest($request, $now);

        $this->assertGreaterThan(90, $range->days());
        $this->assertSame('month', $range->granularity());
    }
}
