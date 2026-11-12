<?php

namespace App\Http\Controllers;

use App\Domain\Display\SectorContext;
use App\Domain\Operations\OdbcConnectionProbe;
use App\Domain\Production\LastValidProductionSnapshotStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class HealthController extends Controller
{
    public function __invoke(LastValidProductionSnapshotStore $snapshots, OdbcConnectionProbe $odbc): JsonResponse
    {
        $cacheAvailable = $this->cacheIsAvailable();
        $odbcConnections = $odbc->check();
        $sectors = [];

        foreach ((array) config('sectors.sectors') as $key => $configuration) {
            if (is_string($key) && is_array($configuration)) {
                $sectors[$key] = $snapshots->healthFor(SectorContext::fromConfiguration($key, $configuration));
            }
        }

        $odbcAvailable = $odbcConnections['production'] === 'ok' && $odbcConnections['dashboard'] === 'ok';

        return response()->json([
            'status' => $cacheAvailable && $odbcAvailable ? 'ok' : 'degraded',
            'application' => 'ok',
            'cache' => $cacheAvailable ? 'ok' : 'unavailable',
            'odbc' => $odbcConnections,
            'production' => $sectors,
        ], $cacheAvailable && $odbcAvailable ? 200 : 503);
    }

    private function cacheIsAvailable(): bool
    {
        try {
            Cache::store((string) config('operations.snapshot_cache_store'))->get('monitor-production:health-check');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
