<?php

namespace App\Domain\Production\Exceptions;

use RuntimeException;
use Throwable;

final class ProductionDataUnavailable extends RuntimeException
{
    private function __construct(
        public readonly string $stage,
        ?Throwable $previous = null,
        public readonly ?string $odbcSqlState = null,
    ) {
        parent::__construct('Production data is temporarily unavailable.', 0, $previous);
    }

    public static function duringConnection(?Throwable $previous = null, ?string $odbcSqlState = null): self
    {
        return new self('connection', $previous, $odbcSqlState);
    }

    public static function duringQuery(?Throwable $previous = null, ?string $odbcSqlState = null): self
    {
        return new self('query', $previous, $odbcSqlState);
    }

    public static function duringBackoff(?string $odbcSqlState = null): self
    {
        return new self('backoff', null, $odbcSqlState);
    }
}
