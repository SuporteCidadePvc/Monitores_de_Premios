<?php

namespace App\Domain\Admin;

final readonly class MonthlyPrizeHistoryRecord
{
    public function __construct(
        public int $id,
        public string $sectorKey,
        public int $year,
        public int $month,
        public float $productionSquares,
        public int $totalHoursHundredths,
        public int $averageHoursHundredths,
        public string $ruleVersion,
        public int $poolCents,
        public int $averagePrizeCents,
        public string $currency,
        public string $calculatedAt,
    ) {}
}
