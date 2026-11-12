<?php

namespace App\Infrastructure\SqlServer;

use PDOException;
use Throwable;

final class OdbcDiagnosticCode
{
    public static function from(Throwable $exception): ?string
    {
        for ($candidate = $exception; $candidate instanceof Throwable; $candidate = $candidate->getPrevious()) {
            if ($candidate instanceof PDOException && is_array($candidate->errorInfo)) {
                $code = self::normalize($candidate->errorInfo[0] ?? null);

                if ($code !== null) {
                    return $code;
                }
            }

            $code = self::normalize($candidate->getCode());

            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    private static function normalize(mixed $code): ?string
    {
        if (! is_string($code)) {
            return null;
        }

        $code = strtoupper($code);

        return preg_match('/^[A-Z0-9]{5}$/', $code) === 1 ? $code : null;
    }
}
