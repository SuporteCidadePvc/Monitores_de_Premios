<?php

namespace App\Domain\Prizes;

use InvalidArgumentException;

final readonly class PrizeMonth
{
    public function __construct(
        public int $year,
        public int $month,
    ) {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('The prize year must be between 2000 and 2100.');
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('The prize month must be between 1 and 12.');
        }
    }
}
