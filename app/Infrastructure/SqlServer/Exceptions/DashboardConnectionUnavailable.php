<?php

namespace App\Infrastructure\SqlServer\Exceptions;

use RuntimeException;
use Throwable;

final class DashboardConnectionUnavailable extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $odbcSqlState = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
