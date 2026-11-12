<?php

namespace App\Infrastructure\SqlServer;

use App\Domain\Prizes\Exceptions\DuplicateMonthlyPrize;
use App\Domain\Prizes\PrizeMonth;
use PDOException;
use Throwable;

final class SqlServerErrorCode
{
    public static function duplicateMonthlyPrize(
        Throwable $exception,
        string $sectorKey,
        PrizeMonth $period,
    ): ?DuplicateMonthlyPrize {
        return self::isUniqueConstraintViolation($exception)
            ? DuplicateMonthlyPrize::for($sectorKey, $period)
            : null;
    }

    public static function isUniqueConstraintViolation(Throwable $exception): bool
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $values = [$current->getMessage(), (string) $current->getCode()];

            if ($current instanceof PDOException && is_array($current->errorInfo)) {
                $values = [...$values, ...array_map(static fn (mixed $value): string => (string) $value, $current->errorInfo)];
            }

            foreach ($values as $value) {
                if (preg_match('/(?:^|\D)(?:2601|2627)(?:\D|$)/', $value) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
