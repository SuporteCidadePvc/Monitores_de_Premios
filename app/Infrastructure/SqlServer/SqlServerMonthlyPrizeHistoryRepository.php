<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use App\Domain\Admin\MonthlyPrizeHistoryPage;
use App\Domain\Admin\MonthlyPrizeHistoryQuery;
use App\Domain\Admin\MonthlyPrizeHistoryRecord;
use App\Domain\Admin\MonthlyPrizeHistoryRepository;
use App\Domain\Admin\MonthlyPrizeHistorySummary;
use App\Domain\Admin\MonthlyPrizeHistorySummaryCalculator;
use App\Infrastructure\SqlServer\Exceptions\DashboardConnectionUnavailable;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final readonly class SqlServerMonthlyPrizeHistoryRepository implements MonthlyPrizeHistoryRepository
{
    public function __construct(
        private DashboardOdbcConnectionFactory $connectionFactory,
        private MonthlyPrizeHistorySummaryCalculator $summaryCalculator,
    ) {}

    public function search(MonthlyPrizeHistoryQuery $query): MonthlyPrizeHistoryPage
    {
        try {
            $connection = $this->connectionFactory->connection();
            [$where, $bindings] = $this->constraints($query);

            $countStatement = $connection->prepare(<<<SQL
                SELECT COUNT(*)
                FROM dbo.MonitorDePremios_Premios_Mensais AS monthly_prize
                INNER JOIN dbo.MonitorDePremios_Setores AS sector ON sector.SectorId = monthly_prize.SectorId
                {$where}
                SQL);
            $this->bind($countStatement, $bindings);
            $countStatement->execute();
            $total = (int) $countStatement->fetchColumn();

            $recordsStatement = $connection->prepare(<<<SQL
                SELECT
                    monthly_prize.MonthlyPrizeId,
                    sector.SectorKey,
                    monthly_prize.PrizeYear,
                    monthly_prize.PrizeMonth,
                    monthly_prize.ProductionSquares,
                    monthly_prize.TotalHours,
                    monthly_prize.AverageHoursPerEmployee,
                    monthly_prize.AppliedRuleVersion,
                    monthly_prize.PrizePoolCents,
                    monthly_prize.AveragePrizeCents,
                    monthly_prize.Currency,
                    monthly_prize.CalculatedAt
                FROM dbo.MonitorDePremios_Premios_Mensais AS monthly_prize
                INNER JOIN dbo.MonitorDePremios_Setores AS sector ON sector.SectorId = monthly_prize.SectorId
                {$where}
                ORDER BY monthly_prize.PrizeYear DESC, monthly_prize.PrizeMonth DESC, sector.SectorKey ASC, monthly_prize.MonthlyPrizeId DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                SQL);
            $this->bind($recordsStatement, $bindings);
            $recordsStatement->bindValue(count($bindings) + 1, $query->offset(), PDO::PARAM_INT);
            $recordsStatement->bindValue(count($bindings) + 2, $query->perPage, PDO::PARAM_INT);
            $recordsStatement->execute();

            return new MonthlyPrizeHistoryPage(
                records: $this->records($recordsStatement),
                total: $total,
                page: $query->page,
                perPage: $query->perPage,
            );
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            throw new AdminDataUnavailable('The monthly prize history could not be read.', 0, $exception);
        }
    }

    /** @return list<MonthlyPrizeHistorySummary> */
    public function summaries(MonthlyPrizeHistoryQuery $query): array
    {
        try {
            $connection = $this->connectionFactory->connection();
            [$where, $bindings] = $this->constraints($query);
            $statement = $connection->prepare(<<<SQL
                SELECT
                    monthly_prize.MonthlyPrizeId,
                    sector.SectorKey,
                    monthly_prize.PrizeYear,
                    monthly_prize.PrizeMonth,
                    monthly_prize.ProductionSquares,
                    monthly_prize.TotalHours,
                    monthly_prize.AverageHoursPerEmployee,
                    monthly_prize.AppliedRuleVersion,
                    monthly_prize.PrizePoolCents,
                    monthly_prize.AveragePrizeCents,
                    monthly_prize.Currency,
                    monthly_prize.CalculatedAt
                FROM dbo.MonitorDePremios_Premios_Mensais AS monthly_prize
                INNER JOIN dbo.MonitorDePremios_Setores AS sector ON sector.SectorId = monthly_prize.SectorId
                {$where}
                ORDER BY sector.SectorKey ASC, monthly_prize.PrizeYear DESC, monthly_prize.PrizeMonth DESC, monthly_prize.MonthlyPrizeId DESC
                SQL);
            $this->bind($statement, $bindings);
            $statement->execute();

            return $this->summaryCalculator->calculate($this->records($statement));
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            throw new AdminDataUnavailable('The monthly prize history could not be read.', 0, $exception);
        }
    }

    public function exportRecords(MonthlyPrizeHistoryQuery $query): iterable
    {
        try {
            $connection = $this->connectionFactory->connection();
            [$where, $bindings] = $this->constraints($query);
            $statement = $connection->prepare(<<<SQL
                SELECT
                    monthly_prize.MonthlyPrizeId,
                    sector.SectorKey,
                    monthly_prize.PrizeYear,
                    monthly_prize.PrizeMonth,
                    monthly_prize.ProductionSquares,
                    monthly_prize.TotalHours,
                    monthly_prize.AverageHoursPerEmployee,
                    monthly_prize.AppliedRuleVersion,
                    monthly_prize.PrizePoolCents,
                    monthly_prize.AveragePrizeCents,
                    monthly_prize.Currency,
                    monthly_prize.CalculatedAt
                FROM dbo.MonitorDePremios_Premios_Mensais AS monthly_prize
                INNER JOIN dbo.MonitorDePremios_Setores AS sector ON sector.SectorId = monthly_prize.SectorId
                {$where}
                ORDER BY monthly_prize.PrizeYear DESC, monthly_prize.PrizeMonth DESC, sector.SectorKey ASC, monthly_prize.MonthlyPrizeId DESC
                SQL);
            $this->bind($statement, $bindings);
            $statement->execute();

            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (is_array($row)) {
                    yield $this->record($row);
                }
            }
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            throw new AdminDataUnavailable('The monthly prize history could not be read.', 0, $exception);
        }
    }

    /** @return array{string, list<string|int>} */
    private function constraints(MonthlyPrizeHistoryQuery $query): array
    {
        $clauses = [];
        $bindings = [];

        if ($query->sectorKey !== null) {
            $clauses[] = 'sector.SectorKey = ?';
            $bindings[] = $query->sectorKey;
        }

        if ($query->fromPeriodNumber() !== null) {
            $clauses[] = '(monthly_prize.PrizeYear * 100 + monthly_prize.PrizeMonth) >= ?';
            $bindings[] = $query->fromPeriodNumber();
        }

        if ($query->toPeriodNumber() !== null) {
            $clauses[] = '(monthly_prize.PrizeYear * 100 + monthly_prize.PrizeMonth) <= ?';
            $bindings[] = $query->toPeriodNumber();
        }

        return [$clauses === [] ? '' : 'WHERE '.implode(' AND ', $clauses), $bindings];
    }

    /** @param list<string|int> $bindings */
    private function bind(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $position => $value) {
            $statement->bindValue($position + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    /** @return list<MonthlyPrizeHistoryRecord> */
    private function records(PDOStatement $statement): array
    {
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): MonthlyPrizeHistoryRecord => $this->record($row), $rows);
    }

    /** @param array<string, mixed> $row */
    private function record(array $row): MonthlyPrizeHistoryRecord
    {
        $row = array_change_key_case($row, CASE_LOWER);

        return new MonthlyPrizeHistoryRecord(
            id: (int) ($row['monthlyprizeid'] ?? 0),
            sectorKey: (string) ($row['sectorkey'] ?? ''),
            year: (int) ($row['prizeyear'] ?? 0),
            month: (int) ($row['prizemonth'] ?? 0),
            productionSquares: (float) ($row['productionsquares'] ?? 0),
            totalHoursHundredths: $this->decimalToHundredths($row['totalhours'] ?? 0),
            averageHoursHundredths: $this->decimalToHundredths($row['averagehoursperemployee'] ?? 0),
            ruleVersion: (string) ($row['appliedruleversion'] ?? ''),
            poolCents: (int) ($row['prizepoolcents'] ?? 0),
            averagePrizeCents: (int) ($row['averageprizecents'] ?? 0),
            currency: (string) ($row['currency'] ?? ''),
            calculatedAt: (string) ($row['calculatedat'] ?? ''),
        );
    }

    private function decimalToHundredths(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
