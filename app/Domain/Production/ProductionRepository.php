<?php

namespace App\Domain\Production;

use App\Domain\Display\SectorContext;

interface ProductionRepository
{
    public function summarize(SectorContext $sector, ProductionDateRange $range): ProductionSnapshot;
}
