<?php

namespace Tests\Unit;

use App\Services\Admin\ActivityRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ActivityRangeTest extends TestCase
{
    public function test_unknown_or_missing_values_default_to_last_thirty_days(): void
    {
        $this->assertSame(ActivityRange::LastThirtyDays, ActivityRange::fromRequest(null));
        $this->assertSame(ActivityRange::LastThirtyDays, ActivityRange::fromRequest('99y'));
        $this->assertSame(ActivityRange::LastSixMonths, ActivityRange::fromRequest('6m'));
    }

    public function test_rolling_ranges_end_now(): void
    {
        $now = CarbonImmutable::parse('2026-10-16 12:00:00');

        $this->assertEquals([$now->subDays(7), $now], ActivityRange::LastSevenDays->bounds($now));
        $this->assertEquals([$now->subDays(30), $now], ActivityRange::LastThirtyDays->bounds($now));
        $this->assertEquals([CarbonImmutable::parse('2026-07-16 12:00:00'), $now], ActivityRange::LastThreeMonths->bounds($now));
        $this->assertEquals([CarbonImmutable::parse('2026-04-16 12:00:00'), $now], ActivityRange::LastSixMonths->bounds($now));
    }

    public function test_last_month_is_the_previous_calendar_month(): void
    {
        [$from, $to] = ActivityRange::LastMonth->bounds(CarbonImmutable::parse('2026-03-31 10:00:00'));

        $this->assertSame('2026-02-01 00:00:00', $from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 23:59:59', $to->format('Y-m-d H:i:s'));
    }

    public function test_all_time_has_no_bounds(): void
    {
        $this->assertSame([null, null], ActivityRange::AllTime->bounds());
    }
}
