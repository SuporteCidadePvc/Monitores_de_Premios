<?php

namespace App\Infrastructure\SqlServer;

use InvalidArgumentException;

final class SqlServerDecimal
{
    public static function fromHundredths(int $hundredths): string
    {
        if ($hundredths < 0) {
            throw new InvalidArgumentException('A decimal value cannot be negative.');
        }

        return sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);
    }
}
