<?php

namespace Tests\Unit;

use App\Domain\Announcements\Exceptions\AnnouncementDataUnavailable;
use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use App\Infrastructure\SqlServer\OdbcDiagnosticCode;
use PDOException;
use Tests\TestCase;

final class OdbcDiagnosticCodeTest extends TestCase
{
    public function test_it_keeps_only_a_standard_sqlstate_from_an_odbc_exception(): void
    {
        $exception = new PDOException('Sensitive driver message');
        $exception->errorInfo = ['IM002', 0, 'Sensitive driver message'];

        $this->assertSame('IM002', OdbcDiagnosticCode::from($exception));
    }

    public function test_it_rejects_non_standard_codes_that_could_contain_driver_details(): void
    {
        $exception = new PDOException('Sensitive driver message');
        $exception->errorInfo = ['server=database;password=secret', 0, 'Sensitive driver message'];

        $this->assertNull(OdbcDiagnosticCode::from($exception));
    }

    public function test_production_and_announcement_failures_carry_the_safe_sqlstate_for_logging(): void
    {
        $production = ProductionDataUnavailable::duringConnection(null, 'IM002');
        $announcement = AnnouncementDataUnavailable::duringConnection('IM002');

        $this->assertSame('IM002', $production->odbcSqlState);
        $this->assertSame('IM002', $announcement->odbcSqlState);
    }
}
