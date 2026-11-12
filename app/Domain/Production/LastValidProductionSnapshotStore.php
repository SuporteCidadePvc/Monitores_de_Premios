<?php

namespace App\Domain\Production;

use App\Domain\Display\SectorContext;

interface LastValidProductionSnapshotStore
{
    public function find(SectorContext $sector, ProductionDateRange $range): ?ProductionSnapshot;

    public function save(SectorContext $sector, ProductionSnapshot $snapshot): void;

    public function recordFailure(SectorContext $sector, string $stage): void;

    /** @return array{state:string,last_success_at:?string,last_failure_at:?string,snapshot_age_seconds:?int} */
    public function healthFor(SectorContext $sector): array;
}
