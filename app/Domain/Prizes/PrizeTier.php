<?php

namespace App\Domain\Prizes;

use InvalidArgumentException;

final readonly class PrizeTier
{
    public function __construct(
        public int $minimumSquares,
        public int $poolCents,
    ) {
        if ($minimumSquares < 0) {
            throw new InvalidArgumentException('The minimum production cannot be negative.');
        }

        if ($poolCents < 0) {
            throw new InvalidArgumentException('The prize pool cannot be negative.');
        }
    }
}
