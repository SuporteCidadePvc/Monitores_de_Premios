<?php

namespace App\Domain\Admin;

use App\Domain\Prizes\PrizeMonth;

final readonly class MonthlyPrizeHistorySummary
{
    /**
     * @param  list<PrizeMonth>  $bestProductionPeriods
     * @param  list<PrizeMonth>  $bestAveragePrizePeriods
     */
    public function __construct(
        public string $sectorKey,
        public int $recordCount,
        public float $averageProductionSquares,
        public float $maximumProductionSquares,
        public array $bestProductionPeriods,
        public int $maximumAveragePrizeCents,
        public array $bestAveragePrizePeriods,
    ) {}
}
