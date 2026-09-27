<?php

namespace Tests\Unit;

use App\Services\Indicators\ComplianceCalculator;
use App\Services\Indicators\Direction;
use App\Services\Indicators\IndicatorSummary;
use App\Services\Indicators\Nature;
use App\Services\Indicators\PeriodInput;
use App\Services\Indicators\PeriodState;
use App\Services\Indicators\TargetSemantics;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComplianceCalculatorTest extends TestCase
{
    private ComplianceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ComplianceCalculator();
    }

    /**
     * @return array<string, array{Direction, float, float, int}>
     */
    public static function periodMatrix(): array
    {
        return [
            'certificados 1T' => [Direction::LowerIsBetter, 27, 15.49, 174],
            'certificados 2T' => [Direction::LowerIsBetter, 27, 25.81, 105],
            'contrataciones 1T' => [Direction::LowerIsBetter, 167, 309.4, 54],
            'contrataciones 2T' => [Direction::LowerIsBetter, 165, 292.1, 56],
            'personas capacitadas' => [Direction::HigherIsBetter, 100, 90, 90],
            'sandwiches como tope' => [Direction::LowerIsBetter, 100, 90, 111],
            'accidentes meta cero' => [Direction::LowerIsBetter, 0, 0, 100],
            'accidentes con incidente' => [Direction::LowerIsBetter, 0, 2, 0],
            'dotacion por encima' => [Direction::TargetIsBetter, 50, 55, 90],
            'dotacion por debajo' => [Direction::TargetIsBetter, 50, 45, 90],
            'dotacion muy lejos' => [Direction::TargetIsBetter, 50, 200, 0],
        ];
    }

    #[DataProvider('periodMatrix')]
    public function test_period_compliance_matches_the_validation_matrix(Direction $direction, float $target, float $measured, int $expectedPercentage): void
    {
        $this->assertSame($expectedPercentage, $this->percentage($this->calculator->compliance($direction, $target, $measured)));
    }

    public function test_same_numbers_give_opposite_results_depending_on_direction(): void
    {
        $higher = $this->calculator->compliance(Direction::HigherIsBetter, 100, 90);
        $lower = $this->calculator->compliance(Direction::LowerIsBetter, 100, 90);

        $this->assertLessThan(1, $higher);
        $this->assertGreaterThan(1, $lower);
    }

    public function test_undefined_ratios_return_null(): void
    {
        $this->assertNull($this->calculator->compliance(Direction::HigherIsBetter, 0, 10));
        $this->assertNull($this->calculator->compliance(Direction::LowerIsBetter, 10, 0));
    }

    public function test_intensive_to_date_reproduces_the_client_spreadsheet(): void
    {
        $duringThirdQuarter = '2026-08-15';

        $certificados = $this->summarize(Direction::LowerIsBetter, Nature::Intensive, [
            $this->period(1, 27, 15.49),
            $this->period(2, 27, 25.81),
            $this->period(3, 25),
            $this->period(4, 21),
        ], $duringThirdQuarter);

        $this->assertSame(27.0, $certificados->toDateTarget);
        $this->assertSame(25.0, $certificados->plannedTarget);
        $this->assertEqualsWithDelta(20.65, $certificados->toDateMeasured, 0.0001);
        $this->assertSame(131, $this->percentage($certificados->toDateCompliance));
        $this->assertSame(31, $this->percentage($certificados->toDateRelativeDeviation()));

        $contrataciones = $this->summarize(Direction::LowerIsBetter, Nature::Intensive, [
            $this->period(1, 167, 309.4),
            $this->period(2, 165, 292.1),
            $this->period(3, 163),
            $this->period(4, 160),
        ], $duringThirdQuarter);

        $this->assertSame(166.0, $contrataciones->toDateTarget);
        $this->assertEqualsWithDelta(300.75, $contrataciones->toDateMeasured, 0.0001);
        $this->assertSame(55, $this->percentage($contrataciones->toDateCompliance));
        $this->assertSame(-45, $this->percentage($contrataciones->toDateRelativeDeviation()));
    }

    public function test_intensive_indicators_have_no_window_progress(): void
    {
        $summary = $this->summarize(Direction::LowerIsBetter, Nature::Intensive, [$this->period(1, 27, 15.49)]);

        $this->assertNull($summary->windowProgress);
    }

    public function test_overdue_period_is_forced_to_zero_instead_of_rewarding_the_missing_value(): void
    {
        $summary = $this->summarize(Direction::LowerIsBetter, Nature::Intensive, [$this->period(1, 27)]);
        $period = $summary->period(1);

        $this->assertSame(PeriodState::Overdue, $period->state);
        $this->assertSame(0.0, $period->compliance);
        $this->assertSame(-1.0, $period->relativeDeviation);
        $this->assertNull($period->absoluteDeviation);
        $this->assertSame(0.0, $summary->toDateCompliance);
        $this->assertSame(1, $summary->overdueCount);
    }

    public function test_overdue_periods_weigh_as_zero_in_the_to_date_result(): void
    {
        $summary = $this->summarize(Direction::LowerIsBetter, Nature::Intensive, [
            $this->period(1, 27, 27),
            $this->period(2, 27),
        ]);

        $this->assertSame(50, $this->percentage($summary->toDateCompliance));
    }

    public function test_skipped_period_is_excluded_from_the_calculation(): void
    {
        $summary = $this->summarize(Direction::LowerIsBetter, Nature::Intensive, [
            $this->period(1, 27, 27),
            $this->period(2, 27, skipped: true),
        ]);

        $this->assertSame(PeriodState::Skipped, $summary->period(2)->state);
        $this->assertNull($summary->period(2)->compliance);
        $this->assertSame(100, $this->percentage($summary->toDateCompliance));
    }

    public function test_period_without_target_is_excluded_even_if_it_has_a_value(): void
    {
        $summary = $this->summarize(Direction::HigherIsBetter, Nature::Extensive, [
            $this->period(1, 100, 100),
            $this->period(2, null, 20),
        ]);

        $this->assertSame(PeriodState::NoTarget, $summary->period(2)->state);
        $this->assertNull($summary->period(2)->compliance);
        $this->assertSame(100.0, $summary->toDateMeasured);
    }

    public function test_future_and_open_periods_are_excluded(): void
    {
        $summary = $this->calculator->summarize(
            Direction::HigherIsBetter,
            Nature::Extensive,
            TargetSemantics::Incremental,
            [
                $this->period(1, 100, 80),
                $this->period(2, 100),
                $this->period(3, 100),
            ],
            CarbonImmutable::parse('2026-05-15'),
        );

        $this->assertSame(PeriodState::Open, $summary->period(2)->state);
        $this->assertSame(PeriodState::Upcoming, $summary->period(3)->state);
        $this->assertSame(0, $summary->overdueCount);
        $this->assertSame(80, $this->percentage($summary->toDateCompliance));
    }

    public function test_extensive_incremental_answers_to_date_and_window_separately(): void
    {
        $summary = $this->calculator->summarize(
            Direction::HigherIsBetter,
            Nature::Extensive,
            TargetSemantics::Incremental,
            [
                $this->period(1, 100, 100),
                $this->period(2, 100, 100),
                $this->period(3, 100),
                $this->period(4, 100),
                $this->period(5, 100),
            ],
            CarbonImmutable::parse('2026-07-15'),
        );

        $this->assertSame(200.0, $summary->toDateTarget);
        $this->assertSame(100, $this->percentage($summary->toDateCompliance));
        $this->assertSame(500.0, $summary->windowTarget);
        $this->assertSame(40, $this->percentage($summary->windowProgress));
    }

    public function test_cumulative_level_compares_the_latest_level_instead_of_the_sum(): void
    {
        $summary = $this->calculator->summarize(
            Direction::HigherIsBetter,
            Nature::Extensive,
            TargetSemantics::CumulativeLevel,
            [
                $this->period(1, 25, 25),
                $this->period(2, 50, 25),
                $this->period(3, 75),
                $this->period(4, 100),
            ],
            CarbonImmutable::parse('2026-07-15'),
        );

        $this->assertSame(50.0, $summary->toDateTarget);
        $this->assertSame(25.0, $summary->toDateMeasured);
        $this->assertSame(50, $this->percentage($summary->toDateCompliance));
        $this->assertSame(100.0, $summary->windowTarget);
        $this->assertSame(25, $this->percentage($summary->windowProgress));
    }

    /**
     * @param  list<PeriodInput>  $periods
     */
    private function summarize(Direction $direction, Nature $nature, array $periods, string $today = '2027-01-15'): IndicatorSummary
    {
        return $this->calculator->summarize($direction, $nature, TargetSemantics::Incremental, $periods, CarbonImmutable::parse($today));
    }

    /**
     * Quarterly periods of 2026.
     */
    private function period(int $number, ?float $target, ?float $measured = null, bool $skipped = false): PeriodInput
    {
        $startsOn = CarbonImmutable::parse('2026-01-01')->addMonths(($number - 1) * 3);

        return new PeriodInput($number, $startsOn, $startsOn->addMonths(3)->subDay(), $target, $measured, $skipped);
    }

    private function percentage(?float $ratio): ?int
    {
        return $ratio === null ? null : (int) round($ratio * 100);
    }
}
