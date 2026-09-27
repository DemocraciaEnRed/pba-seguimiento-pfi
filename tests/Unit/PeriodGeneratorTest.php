<?php

namespace Tests\Unit;

use App\Services\Indicators\PeriodGenerator;
use App\Services\Indicators\PeriodType;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PeriodGeneratorTest extends TestCase
{
    /**
     * @return array<string, array{PeriodType, string, string}>
     */
    public static function firstPeriodBoundaries(): array
    {
        return [
            'mensual' => [PeriodType::Monthly, '2026-01-01', '2026-01-31'],
            'bimestral' => [PeriodType::Bimonthly, '2026-01-01', '2026-02-28'],
            'trimestral' => [PeriodType::Quarterly, '2026-01-01', '2026-03-31'],
            'cuatrimestral' => [PeriodType::FourMonthly, '2026-01-01', '2026-04-30'],
            'semestral' => [PeriodType::Biannual, '2026-01-01', '2026-06-30'],
            'anual' => [PeriodType::Annual, '2026-01-01', '2026-12-31'],
        ];
    }

    #[DataProvider('firstPeriodBoundaries')]
    public function test_each_period_type_has_the_right_length(PeriodType $type, string $expectedStart, string $expectedEnd): void
    {
        $periods = (new PeriodGenerator())->generate($type, CarbonImmutable::parse('2026-01-01'), 1);

        $this->assertSame($expectedStart, $periods[0]['starts_on']->toDateString());
        $this->assertSame($expectedEnd, $periods[0]['ends_on']->toDateString());
    }

    public function test_periods_are_contiguous_numbered_and_cross_years(): void
    {
        $periods = (new PeriodGenerator())->generate(PeriodType::FourMonthly, CarbonImmutable::parse('2026-04-01'), 7);

        $this->assertCount(7, $periods);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_column($periods, 'number'));
        $this->assertSame('2026-04-01', $periods[0]['starts_on']->toDateString());
        $this->assertSame('2026-11-30', $periods[1]['ends_on']->toDateString());
        $this->assertSame('2026-12-01', $periods[2]['starts_on']->toDateString());
        $this->assertSame('2028-07-31', $periods[6]['ends_on']->toDateString());

        for ($index = 1; $index < count($periods); $index++) {
            $this->assertSame(
                $periods[$index - 1]['ends_on']->addDay()->toDateString(),
                $periods[$index]['starts_on']->toDateString(),
            );
        }
    }

    public function test_start_is_normalized_to_the_first_day_of_the_month(): void
    {
        $periods = (new PeriodGenerator())->generate(PeriodType::Monthly, CarbonImmutable::parse('2028-01-31'), 2);

        $this->assertSame('2028-01-01', $periods[0]['starts_on']->toDateString());
        $this->assertSame('2028-02-29', $periods[1]['ends_on']->toDateString());
    }
}
