<?php

namespace App\Domain\Prizes\Exceptions;

use App\Domain\Prizes\PrizeMonth;
use RuntimeException;

final class DuplicateMonthlyPrize extends RuntimeException
{
    public static function for(string $sectorKey, PrizeMonth $period): self
    {
        return new self(sprintf(
            'A prize already exists for sector [%s] in [%04d-%02d].',
            $sectorKey,
            $period->year,
            $period->month,
        ));
    }
}
