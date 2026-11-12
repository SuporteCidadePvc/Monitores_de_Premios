<?php

namespace App\Domain\Production;

use DateTimeImmutable;

final readonly class ProductionSnapshot
{
    public function __construct(
        public float $squares,
        public int $frames,
        public bool $hasData,
        public ProductionDateRange $range,
        public DateTimeImmutable $retrievedAt,
        public bool $isCached = false,
    ) {}
}
