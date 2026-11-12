<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Prizes\Exceptions\PrizeRuleDataUnavailable;
use App\Domain\Prizes\PrizeRuleProvider;
use App\Domain\Prizes\PrizeRuleSet;
use App\Domain\Prizes\PrizeTier;
use App\Infrastructure\SqlServer\Exceptions\DashboardConnectionUnavailable;
use PDOException;
use Throwable;

final readonly class SqlServerPrizeRuleProvider implements PrizeRuleProvider
{
    private const RULES_QUERY = <<<'SQL'
        SELECT prize_rule.RuleVersion, prize_rule.Currency, prize_rule.MinimumSquares, prize_rule.PrizePoolCents
        FROM dbo.MonitorDePremios_Regras_Premio AS prize_rule
        INNER JOIN dbo.MonitorDePremios_Setores AS sector ON sector.SectorId = prize_rule.SectorId
        WHERE sector.SectorKey = ?
          AND sector.IsActive = 1
          AND prize_rule.IsActive = 1
        ORDER BY prize_rule.MinimumSquares ASC, prize_rule.PrizeRuleId ASC
        SQL;

    public function __construct(private DashboardOdbcConnectionFactory $connectionFactory) {}

    public function forSector(string $sectorKey): PrizeRuleSet
    {
        try {
            $statement = $this->connectionFactory->connection()->prepare(self::RULES_QUERY);
            $statement->execute([$sectorKey]);
            $rows = $statement->fetchAll();

            if (! is_array($rows) || $rows === []) {
                throw new PrizeRuleDataUnavailable('No active prize rules are available.');
            }

            $first = array_change_key_case($rows[0], CASE_LOWER);
            $version = (string) ($first['ruleversion'] ?? '');
            $currency = (string) ($first['currency'] ?? '');
            $tiers = [];

            foreach ($rows as $row) {
                $row = array_change_key_case($row, CASE_LOWER);

                if ((string) ($row['ruleversion'] ?? '') !== $version || (string) ($row['currency'] ?? '') !== $currency) {
                    throw new PrizeRuleDataUnavailable('The active prize rules are inconsistent.');
                }

                $tiers[] = new PrizeTier(
                    minimumSquares: (int) ($row['minimumsquares'] ?? -1),
                    poolCents: (int) ($row['prizepoolcents'] ?? -1),
                );
            }

            return new PrizeRuleSet($version, $currency, $tiers);
        } catch (PrizeRuleDataUnavailable $exception) {
            throw $exception;
        } catch (DashboardConnectionUnavailable|PDOException $exception) {
            throw new PrizeRuleDataUnavailable('The prize rule database is unavailable.', 0, $exception);
        } catch (Throwable $exception) {
            throw new PrizeRuleDataUnavailable('The prize rules could not be read.', 0, $exception);
        }
    }
}
