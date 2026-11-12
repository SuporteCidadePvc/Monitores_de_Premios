<?php

namespace Tests\Unit;

use App\Domain\Prizes\Exceptions\DuplicateMonthlyPrize;
use App\Domain\Prizes\PrizeMonth;
use App\Infrastructure\SqlServer\SqlServerDecimal;
use App\Infrastructure\SqlServer\SqlServerErrorCode;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SqlServerMonthlyPrizePersistenceTest extends TestCase
{
    #[DataProvider('hundredths')]
    public function test_hundredths_are_converted_to_sql_decimals_without_floats(int $value, string $expected): void
    {
        $this->assertSame($expected, SqlServerDecimal::fromHundredths($value));
    }

    public static function hundredths(): array
    {
        return [
            'zero' => [0, '0.00'],
            'one hundredth' => [1, '0.01'],
            'one decimal digit' => [1600, '16.00'],
            'large hours value' => [480012, '4800.12'],
        ];
    }

    #[DataProvider('uniqueViolationMessages')]
    public function test_sql_server_unique_violations_are_recognized(string $message): void
    {
        $mapped = SqlServerErrorCode::duplicateMonthlyPrize(
            new PDOException($message),
            'pvc',
            new PrizeMonth(2026, 8),
        );

        $this->assertInstanceOf(DuplicateMonthlyPrize::class, $mapped);
        $this->assertStringContainsString('pvc', $mapped->getMessage());
        $this->assertStringContainsString('2026-08', $mapped->getMessage());
    }

    public static function uniqueViolationMessages(): array
    {
        return [
            'constraint violation 2627' => ['[Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Violation of UNIQUE KEY constraint. (2627)'],
            'duplicate key 2601' => ['[Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Cannot insert duplicate key row. (2601)'],
        ];
    }

    public function test_unrelated_database_errors_are_not_classified_as_duplicates(): void
    {
        $this->assertFalse(SqlServerErrorCode::isUniqueConstraintViolation(
            new PDOException('[Microsoft][ODBC Driver 18 for SQL Server]Login timeout expired'),
        ));
    }
}
