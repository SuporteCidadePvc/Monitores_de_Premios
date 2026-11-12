<?php

namespace Tests\Integration;

use App\Domain\Prizes\PrizeRuleSet;
use App\Infrastructure\SqlServer\SqlServerAdminManagementRepository;
use App\Infrastructure\SqlServer\SqlServerPrizeRuleProvider;
use Tests\TestCase;

final class OdbcAdminManagementRepositoryTest extends TestCase
{
    public function test_it_reads_the_admin_management_data_from_the_dashboard_dsn(): void
    {
        if (! filter_var(env('RUN_ODBC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('ODBC integration tests are disabled.');
        }

        $dashboard = $this->app->make(SqlServerAdminManagementRepository::class)->dashboard();

        $this->assertArrayHasKey('rules', $dashboard);
        $this->assertArrayHasKey('announcements', $dashboard);
        $this->assertArrayHasKey('audit', $dashboard);
        $this->assertIsArray($dashboard['rules']);
    }

    public function test_it_reads_the_active_prize_rules_from_the_dashboard_dsn(): void
    {
        if (! filter_var(env('RUN_ODBC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('ODBC integration tests are disabled.');
        }

        $rules = $this->app->make(SqlServerPrizeRuleProvider::class)->forSector('pvc');

        $this->assertInstanceOf(PrizeRuleSet::class, $rules);
        $this->assertNotEmpty($rules->tiers);
    }
}
