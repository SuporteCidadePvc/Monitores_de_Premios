<?php

namespace Tests\Integration;

use App\Domain\Admin\MonthlyPrizeHistoryQuery;
use App\Infrastructure\SqlServer\SqlServerMonthlyPrizeHistoryRepository;
use Tests\TestCase;

final class OdbcMonthlyPrizeHistoryRepositoryTest extends TestCase
{
    public function test_it_reads_a_paginated_monthly_prize_history_from_the_dashboard_dsn(): void
    {
        if (! filter_var(env('RUN_ODBC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('ODBC integration tests are disabled.');
        }

        $history = $this->app->make(SqlServerMonthlyPrizeHistoryRepository::class)->search(
            new MonthlyPrizeHistoryQuery(perPage: 12),
        );

        $this->assertGreaterThanOrEqual(0, $history->total);
        $this->assertLessThanOrEqual(12, count($history->records));

        foreach ($history->records as $record) {
            $this->assertContains($record->sectorKey, ['pvc', 'aluminio']);
            $this->assertGreaterThanOrEqual(2000, $record->year);
            $this->assertGreaterThanOrEqual(1, $record->month);
            $this->assertLessThanOrEqual(12, $record->month);
        }
    }

    public function test_it_reads_per_sector_history_summaries_from_the_dashboard_dsn(): void
    {
        if (! filter_var(env('RUN_ODBC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('ODBC integration tests are disabled.');
        }

        $summaries = $this->app->make(SqlServerMonthlyPrizeHistoryRepository::class)->summaries(
            new MonthlyPrizeHistoryQuery,
        );

        $this->assertIsArray($summaries);

        foreach ($summaries as $summary) {
            $this->assertContains($summary->sectorKey, ['pvc', 'aluminio']);
            $this->assertGreaterThan(0, $summary->recordCount);
            $this->assertGreaterThanOrEqual(0, $summary->averageProductionSquares);
        }
    }

    public function test_it_streams_all_history_records_for_an_xlsx_export_from_the_dashboard_dsn(): void
    {
        if (! filter_var(env('RUN_ODBC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('ODBC integration tests are disabled.');
        }

        $records = iterator_to_array(
            $this->app->make(SqlServerMonthlyPrizeHistoryRepository::class)->exportRecords(new MonthlyPrizeHistoryQuery),
        );

        $this->assertIsArray($records);

        foreach ($records as $record) {
            $this->assertContains($record->sectorKey, ['pvc', 'aluminio']);
            $this->assertGreaterThanOrEqual(2000, $record->year);
            $this->assertGreaterThanOrEqual(1, $record->month);
            $this->assertLessThanOrEqual(12, $record->month);
        }
    }
}
