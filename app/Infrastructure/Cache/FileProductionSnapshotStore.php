<?php

namespace App\Infrastructure\Cache;

use App\Domain\Display\SectorContext;
use App\Domain\Production\LastValidProductionSnapshotStore;
use App\Domain\Production\ProductionDateRange;
use App\Domain\Production\ProductionSnapshot;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

final readonly class FileProductionSnapshotStore implements LastValidProductionSnapshotStore
{
    public function __construct(private Repository $cache) {}

    public function find(SectorContext $sector, ProductionDateRange $range): ?ProductionSnapshot
    {
        try {
            $value = $this->cache->get($this->snapshotKey($sector, $range));

            if (! is_array($value)) {
                return null;
            }

            return new ProductionSnapshot(
                squares: (float) ($value['squares'] ?? 0),
                frames: (int) ($value['frames'] ?? 0),
                hasData: (bool) ($value['has_data'] ?? false),
                range: $range,
                retrievedAt: new DateTimeImmutable((string) ($value['retrieved_at'] ?? '')),
                isCached: true,
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function save(SectorContext $sector, ProductionSnapshot $snapshot): void
    {
        try {
            $this->cache->forever($this->snapshotKey($sector, $snapshot->range), [
                'squares' => $snapshot->squares,
                'frames' => $snapshot->frames,
                'has_data' => $snapshot->hasData,
                'retrieved_at' => $snapshot->retrievedAt->format(DATE_ATOM),
            ]);
            $this->cache->forever($this->healthKey($sector), [
                'state' => 'ok',
                'last_success_at' => $snapshot->retrievedAt->format(DATE_ATOM),
                'last_failure_at' => null,
            ]);
        } catch (Throwable) {
            // Cache problems must not make a valid production result unavailable.
        }
    }

    public function recordFailure(SectorContext $sector, string $stage): void
    {
        try {
            $previous = $this->cache->get($this->healthKey($sector));
            $previous = is_array($previous) ? $previous : [];
            $this->cache->forever($this->healthKey($sector), [
                'state' => 'degraded',
                'last_success_at' => $previous['last_success_at'] ?? null,
                'last_failure_at' => now((string) config('production.timezone'))->toAtomString(),
                'last_failure_stage' => $stage,
            ]);
        } catch (Throwable) {
            // The original production failure remains the relevant result.
        }
    }

    public function healthFor(SectorContext $sector): array
    {
        try {
            $value = $this->cache->get($this->healthKey($sector));
            $value = is_array($value) ? $value : [];
            $lastSuccess = isset($value['last_success_at']) && is_string($value['last_success_at'])
                ? new DateTimeImmutable($value['last_success_at'])
                : null;

            return [
                'state' => (string) ($value['state'] ?? 'unknown'),
                'last_success_at' => $lastSuccess?->format(DATE_ATOM),
                'last_failure_at' => isset($value['last_failure_at']) ? (string) $value['last_failure_at'] : null,
                'snapshot_age_seconds' => $lastSuccess === null ? null : max(0, now()->getTimestamp() - $lastSuccess->getTimestamp()),
            ];
        } catch (Throwable) {
            return [
                'state' => 'unknown',
                'last_success_at' => null,
                'last_failure_at' => null,
                'snapshot_age_seconds' => null,
            ];
        }
    }

    private function snapshotKey(SectorContext $sector, ProductionDateRange $range): string
    {
        return 'monitor-production:snapshot:'.$sector->key.':'.sha1(
            $range->start->format(DATE_ATOM).'|'.$range->endExclusive->format(DATE_ATOM),
        );
    }

    private function healthKey(SectorContext $sector): string
    {
        return 'monitor-production:health:'.$sector->key;
    }
}
