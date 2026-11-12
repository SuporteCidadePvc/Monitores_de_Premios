<?php

namespace App\Domain\Prizes;

final readonly class PrizePoolResult
{
    public function __construct(
        public string $ruleVersion,
        public string $currency,
        public ?int $minimumSquares,
        public int $poolCents,
    ) {}
}
