<?php

namespace App\Domain\Prizes;

final readonly class PrizeCalculationResult
{
    public function __construct(
        public string $sectorKey,
        public PrizeMonth $period,
        public float $squares,
        public int $totalHoursHundredths,
        public int $averageHoursHundredths,
        public string $ruleVersion,
        public string $currency,
        public ?int $minimumSquares,
        public int $poolCents,
        public int $averagePrizeCents,
    ) {}
}
