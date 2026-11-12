<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Admin\AdminUser;
use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use App\Domain\Admin\Exceptions\AdminOperationFailed;
use App\Domain\Prizes\Exceptions\DuplicateMonthlyPrize;
use App\Domain\Prizes\MonthlyPrizeRepository;
use App\Domain\Prizes\PrizeCalculationResult;
use App\Domain\Prizes\PrizeMonth;
use App\Domain\Prizes\WorkedHours;
use App\Infrastructure\SqlServer\Exceptions\DashboardConnectionUnavailable;
use PDO;
use PDOException;
use Throwable;

final readonly class SqlServerMonthlyPrizeRepository implements MonthlyPrizeRepository
{
    public function __construct(private DashboardOdbcConnectionFactory $connectionFactory) {}

    public function find(string $sectorKey, PrizeMonth $period): ?PrizeCalculationResult
    {
        try {
            $statement = $this->connectionFactory->connection()->prepare(<<<'SQL'
                SELECT sector.SectorKey, monthly_prize.PrizeYear, monthly_prize.PrizeMonth,
                       monthly_prize.ProductionSquares, monthly_prize.TotalHours,
                       monthly_prize.AverageHoursPerEmployee, monthly_prize.AppliedRuleVersion,
                       applied_rule.MinimumSquares, monthly_prize.PrizePoolCents,
                       monthly_prize.AveragePrizeCents, monthly_prize.Currency
                FROM dbo.MonitorDePremios_Premios_Mensais AS monthly_prize
                INNER JOIN dbo.MonitorDePremios_Setores AS sector ON sector.SectorId = monthly_prize.SectorId
                LEFT JOIN dbo.MonitorDePremios_Regras_Premio AS applied_rule
                    ON applied_rule.PrizeRuleId = monthly_prize.AppliedPrizeRuleId
                WHERE sector.SectorKey = ?
                  AND monthly_prize.PrizeYear = ?
                  AND monthly_prize.PrizeMonth = ?
                SQL);
            $statement->execute([$sectorKey, $period->year, $period->month]);
            $row = $statement->fetch();

            if (! is_array($row)) {
                return null;
            }

            $row = array_change_key_case($row, CASE_LOWER);

            return new PrizeCalculationResult(
                sectorKey: (string) $row['sectorkey'],
                period: new PrizeMonth((int) $row['prizeyear'], (int) $row['prizemonth']),
                squares: (float) $row['productionsquares'],
                totalHoursHundredths: WorkedHours::fromDecimal((string) $row['totalhours'])->hundredths,
                averageHoursHundredths: WorkedHours::fromDecimal((string) $row['averagehoursperemployee'])->hundredths,
                ruleVersion: (string) $row['appliedruleversion'],
                currency: (string) $row['currency'],
                minimumSquares: $row['minimumsquares'] === null ? null : (int) $row['minimumsquares'],
                poolCents: (int) $row['prizepoolcents'],
                averagePrizeCents: (int) $row['averageprizecents'],
            );
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            throw new AdminDataUnavailable('The monthly prize could not be read.', 0, $exception);
        }
    }

    public function add(PrizeCalculationResult $result, AdminUser $actor): void
    {
        try {
            $connection = $this->connectionFactory->connection();
            $connection->beginTransaction();

            $sectorId = $this->sectorId($connection, $result->sectorKey);
            $appliedRuleId = $this->appliedRuleId($connection, $sectorId, $result);
            $totalHours = SqlServerDecimal::fromHundredths($result->totalHoursHundredths);
            $averageHours = SqlServerDecimal::fromHundredths($result->averageHoursHundredths);
            $productionSquares = number_format($result->squares, 2, '.', '');

            $statement = $connection->prepare(<<<'SQL'
                INSERT INTO dbo.MonitorDePremios_Premios_Mensais
                    (SectorId, PrizeYear, PrizeMonth, ProductionSquares, TotalHours,
                     AverageHoursPerEmployee, AppliedRuleVersion, AppliedPrizeRuleId,
                     PrizePoolCents, AveragePrizeCents, Currency)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                SQL);
            $statement->execute([
                $sectorId,
                $result->period->year,
                $result->period->month,
                $productionSquares,
                $totalHours,
                $averageHours,
                $result->ruleVersion,
                $appliedRuleId,
                $result->poolCents,
                $result->averagePrizeCents,
                $result->currency,
            ]);

            $id = (int) $connection->query('SELECT CAST(SCOPE_IDENTITY() AS BIGINT)')->fetchColumn();
            $this->audit($connection, $id, $sectorId, $actor, [
                'sector' => $result->sectorKey,
                'period' => sprintf('%04d-%02d', $result->period->year, $result->period->month),
                'production_squares' => $productionSquares,
                'total_hours' => $totalHours,
                'average_hours_per_employee' => $averageHours,
                'rule_version' => $result->ruleVersion,
                'prize_pool_cents' => $result->poolCents,
                'average_prize_cents' => $result->averagePrizeCents,
            ]);

            $connection->commit();
        } catch (DuplicateMonthlyPrize $exception) {
            $this->rollBack($connection ?? null);

            throw $exception;
        } catch (DashboardConnectionUnavailable $exception) {
            $this->rollBack($connection ?? null);

            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (PDOException $exception) {
            $this->rollBack($connection ?? null);

            $duplicate = SqlServerErrorCode::duplicateMonthlyPrize($exception, $result->sectorKey, $result->period);

            if ($duplicate instanceof DuplicateMonthlyPrize) {
                throw $duplicate;
            }

            throw new AdminDataUnavailable('The administrative database is unavailable.', 0, $exception);
        } catch (AdminOperationFailed $exception) {
            $this->rollBack($connection ?? null);

            throw $exception;
        } catch (Throwable $exception) {
            $this->rollBack($connection ?? null);

            throw new AdminOperationFailed('The monthly prize could not be recorded.', 0, $exception);
        }
    }

    private function sectorId(PDO $connection, string $sectorKey): int
    {
        $statement = $connection->prepare(
            'SELECT SectorId FROM dbo.MonitorDePremios_Setores WHERE SectorKey = ? AND IsActive = 1',
        );
        $statement->execute([$sectorKey]);
        $sectorId = $statement->fetchColumn();

        if ($sectorId === false) {
            throw new AdminOperationFailed('The selected sector is unavailable.');
        }

        return (int) $sectorId;
    }

    private function appliedRuleId(PDO $connection, int $sectorId, PrizeCalculationResult $result): ?int
    {
        if ($result->minimumSquares === null) {
            return null;
        }

        $statement = $connection->prepare(<<<'SQL'
            SELECT PrizeRuleId
            FROM dbo.MonitorDePremios_Regras_Premio
            WHERE SectorId = ? AND RuleVersion = ? AND MinimumSquares = ? AND IsActive = 1
            SQL);
        $statement->execute([$sectorId, $result->ruleVersion, $result->minimumSquares]);
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (! is_array($ids) || count($ids) !== 1) {
            throw new AdminOperationFailed('The applied prize rule is unavailable.');
        }

        return (int) $ids[0];
    }

    /** @param array<string, int|string> $metadata */
    private function audit(PDO $connection, int $id, int $sectorId, AdminUser $actor, array $metadata): void
    {
        $statement = $connection->prepare(<<<'SQL'
            INSERT INTO dbo.MonitorDePremios_Auditoria
                (ActionName, EntityType, EntityId, SectorId, ActorLabel, MetadataJson)
            VALUES (?, ?, ?, ?, ?, ?)
            SQL);
        $statement->execute([
            'monthly_prize.recorded',
            'monthly_prize',
            $id,
            $sectorId,
            $actor->displayName,
            json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function rollBack(?PDO $connection): void
    {
        if ($connection instanceof PDO && $connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}
