<?php

namespace App\Domain\Prizes;

use InvalidArgumentException;

final readonly class PrizeCalculationInput
{
    public function __construct(
        public string $sectorKey,
        public PrizeMonth $period,
        public float $squares,
        public WorkedHours $totalHours,
        public WorkedHours $averageHoursPerEmployee,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $sectorKey)) {
            throw new InvalidArgumentException('The sector key is invalid.');
        }

        if (! is_finite($squares) || $squares < 0) {
            throw new InvalidArgumentException('Production must be a finite, non-negative number.');
        }

        if ($totalHours->hundredths === 0) {
            throw new InvalidArgumentException('Total hours must be greater than zero.');
        }

        if ($averageHoursPerEmployee->hundredths > $totalHours->hundredths) {
            throw new InvalidArgumentException('Average employee hours cannot exceed total hours.');
        }
    }
}
