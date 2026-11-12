<?php

namespace App\Domain\Announcements\Exceptions;

use RuntimeException;

final class AnnouncementDataUnavailable extends RuntimeException
{
    private function __construct(
        public readonly string $stage,
        public readonly ?string $odbcSqlState = null,
    )
    {
        parent::__construct('Announcement data is temporarily unavailable.');
    }

    public static function duringConnection(?string $odbcSqlState = null): self
    {
        return new self('connection', $odbcSqlState);
    }

    public static function duringQuery(?string $odbcSqlState = null): self
    {
        return new self('query', $odbcSqlState);
    }

    public static function duringBackoff(?string $odbcSqlState = null): self
    {
        return new self('backoff', $odbcSqlState);
    }
}
