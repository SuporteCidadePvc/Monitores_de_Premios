<?php

namespace App\Domain\Prizes\Exceptions;

use App\Domain\Prizes\PrizeMonth;
use RuntimeException;

final class MonthlyPrizeClosingNotAllowed extends RuntimeException
{
    public static function monthIsNotClosed(PrizeMonth $period): self
    {
        return new self(sprintf(
            'The prize month [%04d-%02d] is not closed.',
            $period->year,
            $period->month,
        ));
    }

    public static function productionHasNoRecords(PrizeMonth $period): self
    {
        return new self(sprintf(
            'No production records were found for [%04d-%02d].',
            $period->year,
            $period->month,
        ));
    }
}
