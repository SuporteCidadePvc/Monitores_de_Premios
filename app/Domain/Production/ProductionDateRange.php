<?php

namespace App\Domain\Production;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProductionDateRange
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $endExclusive,
    ) {
        if ($endExclusive <= $start) {
            throw new InvalidArgumentException('The production range end must be after its start.');
        }
    }
}
