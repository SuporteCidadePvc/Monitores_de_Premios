<?php

namespace Tests\Unit;

use App\Domain\Admin\AdminUser;
use App\Domain\Prizes\Exceptions\DuplicateMonthlyPrize;
use App\Domain\Prizes\MonthlyPrizeService;
use App\Domain\Prizes\PrizeCalculationInput;
use App\Domain\Prizes\PrizeCalculator;
use App\Domain\Prizes\PrizeMonth;
use App\Domain\Prizes\PrizeRuleSet;
use App\Domain\Prizes\PrizeTier;
use App\Domain\Prizes\WorkedHours;
use App\Infrastructure\Config\ConfiguredPrizeRuleProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\InMemoryMonthlyPrizeRepository;
use Tests\TestCase;

final class PrizeCalculatorTest extends TestCase
{
    #[DataProvider('productionBoundaries')]
    public function test_every_production_boundary_selects_the_expected_pool(float $squares, int $expectedPoolCents): void
    {
        $calculator = new PrizeCalculator;
        $pool = $calculator->selectPool($this->rules(), $squares);

        $this->assertSame($expectedPoolCents, $pool->poolCents);
        $this->assertSame('premio-producao-v1', $pool->ruleVersion);
        $this->assertSame('EUR', $pool->currency);
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function productionBoundaries(): iterable
    {
        yield 'below first tier' => [2299.99, 0];
        yield 'first tier' => [2300.0, 80000];
        yield 'below second tier' => [2529.99, 80000];
        yield 'second tier' => [2530.0, 120000];
        yield 'below third tier' => [2759.99, 120000];
        yield 'third tier' => [2760.0, 180000];
        yield 'below fourth tier' => [2989.99, 180000];
        yield 'fourth tier' => [2990.0, 270000];
        yield 'below fifth tier' => [3219.99, 270000];
        yield 'fifth tier' => [3220.0, 400000];
        yield 'below sixth tier' => [3449.99, 400000];
        yield 'sixth tier' => [3450.0, 570000];
        yield 'below seventh tier' => [3679.99, 570000];
        yield 'seventh tier' => [3680.0, 800000];
        yield 'above seventh tier' => [5000.0, 800000];
    }

    public function test_average_prize_uses_integer_money_and_server_side_hours(): void
    {
        $input = new PrizeCalculationInput(
            sectorKey: 'pvc',
            period: new PrizeMonth(2026, 8),
            squares: 2990.0,
            totalHours: WorkedHours::fromDecimal('4800'),
            averageHoursPerEmployee: WorkedHours::fromDecimal('160'),
        );

        $result = (new PrizeCalculator)->calculate($this->rules(), $input);

        $this->assertSame(270000, $result->poolCents);
        $this->assertSame(9000, $result->averagePrizeCents);
        $this->assertSame(480000, $result->totalHoursHundredths);
        $this->assertSame(16000, $result->averageHoursHundredths);
        $this->assertSame('premio-producao-v1', $result->ruleVersion);
    }

    public function test_final_cent_is_rounded_half_up_without_storing_floats(): void
    {
        $input = new PrizeCalculationInput(
            sectorKey: 'pvc',
            period: new PrizeMonth(2026, 8),
            squares: 2300.0,
            totalHours: WorkedHours::fromDecimal('3'),
            averageHoursPerEmployee: WorkedHours::fromDecimal('1'),
        );

        $result = (new PrizeCalculator)->calculate($this->rules(), $input);

        $this->assertSame(26667, $result->averagePrizeCents);
    }

    public function test_zero_total_hours_are_rejected_before_division(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total hours must be greater than zero.');

        new PrizeCalculationInput(
            sectorKey: 'pvc',
            period: new PrizeMonth(2026, 8),
            squares: 2990.0,
            totalHours: WorkedHours::fromDecimal('0'),
            averageHoursPerEmployee: WorkedHours::fromDecimal('0'),
        );
    }

    public function test_employee_hours_cannot_exceed_all_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Average employee hours cannot exceed total hours.');

        new PrizeCalculationInput(
            sectorKey: 'pvc',
            period: new PrizeMonth(2026, 8),
            squares: 2990.0,
            totalHours: WorkedHours::fromDecimal('100'),
            averageHoursPerEmployee: WorkedHours::fromDecimal('100.01'),
        );
    }

    public function test_hours_accept_portuguese_decimal_separator_and_preserve_two_decimals(): void
    {
        $hours = WorkedHours::fromDecimal('160,25');

        $this->assertSame(16025, $hours->hundredths);
        $this->assertSame('160.25', $hours->toDecimal());
    }

    #[DataProvider('invalidHours')]
    public function test_invalid_or_unsafe_hours_are_rejected(string $hours): void
    {
        $this->expectException(InvalidArgumentException::class);

        WorkedHours::fromDecimal($hours);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHours(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'too many decimal places' => ['1.001'];
        yield 'not numeric' => ['dez'];
        yield 'empty' => [''];
        yield 'unsafe maximum' => ['1000000.01'];
    }

    public function test_configured_rules_are_explicit_for_both_sectors(): void
    {
        $provider = new ConfiguredPrizeRuleProvider((array) config('prizes'));

        $this->assertSame('premio-producao-v1', $provider->forSector('pvc')->version);
        $this->assertSame('premio-producao-aluminio-v1', $provider->forSector('aluminio')->version);
        $this->assertNotSame($provider->forSector('pvc')->tiers, []);
        $this->assertSame(400, $provider->forSector('aluminio')->tiers[0]->minimumSquares);
        $this->assertSame(160000, $provider->forSector('aluminio')->tiers[6]->poolCents);
    }

    public function test_monthly_prize_service_rejects_a_duplicate_sector_and_month(): void
    {
        $repository = new InMemoryMonthlyPrizeRepository;
        $service = new MonthlyPrizeService(
            new ConfiguredPrizeRuleProvider((array) config('prizes')),
            new PrizeCalculator,
            $repository,
        );
        $input = new PrizeCalculationInput(
            sectorKey: 'pvc',
            period: new PrizeMonth(2026, 8),
            squares: 2990.0,
            totalHours: WorkedHours::fromDecimal('4800'),
            averageHoursPerEmployee: WorkedHours::fromDecimal('160'),
        );

        $actor = new AdminUser(1, 'admin', 'Admin', 'hash', true);
        $service->record($input, $actor);

        $this->expectException(DuplicateMonthlyPrize::class);
        $service->record($input, $actor);
    }

    public function test_same_month_remains_independent_between_sectors(): void
    {
        $repository = new InMemoryMonthlyPrizeRepository;
        $service = new MonthlyPrizeService(
            new ConfiguredPrizeRuleProvider((array) config('prizes')),
            new PrizeCalculator,
            $repository,
        );

        foreach (['pvc', 'aluminio'] as $sectorKey) {
            $service->record(new PrizeCalculationInput(
                sectorKey: $sectorKey,
                period: new PrizeMonth(2026, 8),
                squares: 2990.0,
                totalHours: WorkedHours::fromDecimal('4800'),
                averageHoursPerEmployee: WorkedHours::fromDecimal('160'),
            ), new AdminUser(1, 'admin', 'Admin', 'hash', true));
        }

        $this->assertNotNull($repository->find('pvc', new PrizeMonth(2026, 8)));
        $this->assertNotNull($repository->find('aluminio', new PrizeMonth(2026, 8)));
    }

    #[DataProvider('invalidProduction')]
    public function test_invalid_production_is_rejected(float $squares): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PrizeCalculator)->selectPool($this->rules(), $squares);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidProduction(): iterable
    {
        yield 'negative' => [-0.01];
        yield 'infinite' => [INF];
        yield 'not a number' => [NAN];
    }

    public function test_duplicate_or_unsorted_tier_limits_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PrizeRuleSet('invalid-v1', 'EUR', [
            new PrizeTier(2300, 80000),
            new PrizeTier(2300, 120000),
        ]);
    }

    private function rules(): PrizeRuleSet
    {
        return (new ConfiguredPrizeRuleProvider((array) config('prizes')))->forSector('pvc');
    }
}
