<?php

namespace App\Infrastructure\Production;

use App\Domain\Display\SectorContext;
use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use App\Domain\Production\LastValidProductionSnapshotStore;
use App\Domain\Production\ProductionDateRange;
use App\Domain\Production\ProductionRepository;
use App\Domain\Production\ProductionSnapshot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ResilientProductionRepository implements ProductionRepository
{
    public function __construct(
        private ProductionRepository $source,
        private LastValidProductionSnapshotStore $snapshots,
        private Repository $cache,
        private int $lockSeconds,
        private int $lockWaitSeconds,
        private int $retryAfterFailureSeconds,
    ) {}

    public function summarize(SectorContext $sector, ProductionDateRange $range): ProductionSnapshot
    {
        $lock = $this->cache->lock($this->lockKey($sector, $range), $this->lockSeconds);

        try {
            return $lock->block($this->lockWaitSeconds, function () use ($sector, $range): ProductionSnapshot {
                return $this->readFreshOrCached($sector, $range);
            });
        } catch (LockTimeoutException) {
            $cached = $this->snapshots->find($sector, $range);

            if ($cached instanceof ProductionSnapshot) {
                return $cached;
            }

            return $this->readFreshOrCached($sector, $range);
        } catch (ProductionDataUnavailable $exception) {
            throw $exception;
        } catch (Throwable) {
            return $this->readFreshOrCached($sector, $range);
        }
    }

    private function readFreshOrCached(SectorContext $sector, ProductionDateRange $range): ProductionSnapshot
    {
        $backoff = $this->backoffState();

        if ($backoff !== null) {
            return $this->cachedOrUnavailable($sector, $range, $backoff['odbc_sqlstate']);
        }

        try {
            $snapshot = $this->source->summarize($sector, $range);
            $this->snapshots->save($sector, $snapshot);

            return $snapshot;
        } catch (ProductionDataUnavailable $exception) {
            $this->startBackoff($exception);
            $this->snapshots->recordFailure($sector, $exception->stage);

            return $this->cachedOrUnavailable($sector, $range, $exception->odbcSqlState, $exception);
        }
    }

    private function cachedOrUnavailable(
        SectorContext $sector,
        ProductionDateRange $range,
        ?string $odbcSqlState,
        ?ProductionDataUnavailable $failure = null,
    ): ProductionSnapshot {
        $cached = $this->snapshots->find($sector, $range);

        if (! $cached instanceof ProductionSnapshot) {
            throw $failure ?? ProductionDataUnavailable::duringBackoff($odbcSqlState);
        }

        if ($failure instanceof ProductionDataUnavailable) {
            Log::warning('Using cached production snapshot after a production data failure.', [
                'sector' => $sector->key,
                'stage' => $failure->stage,
                'odbc_sqlstate' => $odbcSqlState,
                'retrieved_at' => $cached->retrievedAt->format(DATE_ATOM),
            ]);
        }

        return $cached;
    }

    /** @return array{odbc_sqlstate:?string}|null */
    private function backoffState(): ?array
    {
        try {
            $state = $this->cache->get('monitor-odbc:production:retry-after-failure');

            if (! is_array($state)) {
                return null;
            }

            $sqlState = $state['odbc_sqlstate'] ?? null;

            return [
                'odbc_sqlstate' => is_string($sqlState) && preg_match('/^[A-Z0-9]{5}$/', $sqlState) === 1
                    ? $sqlState
                    : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function startBackoff(ProductionDataUnavailable $exception): void
    {
        try {
            $this->cache->put('monitor-odbc:production:retry-after-failure', [
                'odbc_sqlstate' => $exception->odbcSqlState,
            ], $this->retryAfterFailureSeconds);
        } catch (Throwable) {
            // A cache failure must not suppress future source retries.
        }
    }

    private function lockKey(SectorContext $sector, ProductionDateRange $range): string
    {
        return 'monitor-production:lock:'.$sector->key.':'.sha1(
            $range->start->format(DATE_ATOM).'|'.$range->endExclusive->format(DATE_ATOM),
        );
    }
}
