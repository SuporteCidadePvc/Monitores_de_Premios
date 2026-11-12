<?php

namespace App\Domain\Production;

enum ProductionPeriod: string
{
    case Today = 'today';
    case Week = 'week';
    case Month = 'month';

    public static function fromScreen(string $screen): ?self
    {
        return match ($screen) {
            'principal', 'mes' => self::Month,
            'hoje' => self::Today,
            'semana' => self::Week,
            default => null,
        };
    }
}
