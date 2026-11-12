<?php

namespace Tests\Integration;

use App\Domain\Display\SectorContext;
use App\Domain\Production\ProductionPeriod;
use App\Domain\Production\ProductionPeriodRangeFactory;
use App\Infrastructure\SqlServer\SqlServerProductionRepository;
use Tests\TestCase;

class OdbcProductionRepositoryTest extends TestCase
{
    public function test_it_reads_the_current_month_for_pvc_from_the_configured_dsn(): void
    {
        $this->assertPeriodCanBeReadFor('pvc', ProductionPeriod::Month);
    }

    public function test_it_reads_all_display_periods_for_aluminium_from_the_configured_dsn(): void
    {
        foreach ([ProductionPeriod::Today, ProductionPeriod::Week, ProductionPeriod::Month] as $period) {
            $this->assertPeriodCanBeReadFor('aluminio', $period);
        }
    }

    private function assertPeriodCanBeReadFor(string $sectorKey, ProductionPeriod $period): void
    {
        if (! filter_var(env('RUN_ODBC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('ODBC integration tests are disabled.');
        }

        $configuration = config("sectors.sectors.{$sectorKey}");

        $this->assertIsArray($configuration);

        $sector = SectorContext::fromConfiguration($sectorKey, $configuration);
        $range = $this->app->make(ProductionPeriodRangeFactory::class)->for($period);
        $snapshot = $this->app->make(SqlServerProductionRepository::class)->summarize($sector, $range);

        $this->assertGreaterThanOrEqual(0, $snapshot->squares);
        $this->assertGreaterThanOrEqual(0, $snapshot->frames);
        $this->assertSame($range, $snapshot->range);
    }
}
