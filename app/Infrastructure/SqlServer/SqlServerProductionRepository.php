<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Display\SectorContext;
use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use App\Domain\Production\ProductionDateRange;
use App\Domain\Production\ProductionRepository;
use App\Domain\Production\ProductionSnapshot;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final readonly class SqlServerProductionRepository implements ProductionRepository
{
    private const PVC_PRODUCTION_QUERY = <<<'SQL'
        SELECT
            COALESCE(SUM(Squares), 0) AS total_squares,
            COALESCE(SUM(Frames), 0) AS total_frames,
            COUNT_BIG(*) AS matched_records
        FROM vwLotSubmodels
        WHERE SubmodelShopExitDate >= ?
          AND SubmodelShopExitDate < ?
          AND ProductionLineName = ?
        SQL;

    /**
     * The PDO_ODBC driver transmits the accented line label through an ANSI path.
     * Build the known label with SQL Server Unicode characters instead of binding it.
     */
    private const ALUMINIUM_PRODUCTION_QUERY = <<<'SQL'
        SELECT
            COALESCE(SUM(Squares), 0) AS total_squares,
            COALESCE(SUM(Frames), 0) AS total_frames,
            COUNT_BIG(*) AS matched_records
        FROM dbo.vwLotSubmodels
        WHERE SubmodelShopExitDate >= ?
          AND SubmodelShopExitDate < ?
          AND RTRIM(ProductionLineName) =
              NCHAR(76) + NCHAR(105) + NCHAR(110) + NCHAR(104) + NCHAR(97)
              + NCHAR(32) + NCHAR(100) + NCHAR(101) + NCHAR(32) + NCHAR(65)
              + NCHAR(108) + NCHAR(117) + NCHAR(109) + NCHAR(237) + NCHAR(110)
              + NCHAR(105) + NCHAR(111)
        SQL;

    public function __construct(
        private OdbcConnectionFactory $connectionFactory,
    ) {}

    public function summarize(SectorContext $sector, ProductionDateRange $range): ProductionSnapshot
    {
        [$query, $lineNameConfigurationKey] = match ($sector->productionQueryKey) {
            'pvc_production_current' => [self::PVC_PRODUCTION_QUERY, 'production.line_names.pvc'],
            'aluminium_production_current' => [self::ALUMINIUM_PRODUCTION_QUERY, null],
            default => throw new InvalidArgumentException('Unsupported production query key.'),
        };

        try {
            $statement = $this->connectionFactory->connection()->prepare($query);
            $statement->bindValue(1, $range->start->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $statement->bindValue(2, $range->endExclusive->format('Y-m-d H:i:s'), PDO::PARAM_STR);

            if (is_string($lineNameConfigurationKey)) {
                $lineName = config($lineNameConfigurationKey);

                if (! is_string($lineName) || $lineName === '') {
                    throw new InvalidArgumentException('The production line name must be configured.');
                }

                $statement->bindValue(3, $lineName, PDO::PARAM_STR);
            }

            $statement->execute();

            $row = $statement->fetch();

            if (! is_array($row)) {
                throw ProductionDataUnavailable::duringQuery();
            }

            $row = array_change_key_case($row, CASE_LOWER);

            return new ProductionSnapshot(
                squares: (float) ($row['total_squares'] ?? 0),
                frames: (int) ($row['total_frames'] ?? 0),
                hasData: (int) ($row['matched_records'] ?? 0) > 0,
                range: $range,
                retrievedAt: new DateTimeImmutable('now', $range->start->getTimezone()),
            );
        } catch (ProductionDataUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ProductionDataUnavailable::duringQuery($exception, OdbcDiagnosticCode::from($exception));
        }
    }
}
