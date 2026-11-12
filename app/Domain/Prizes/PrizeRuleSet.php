<?php

namespace App\Domain\Prizes;

use InvalidArgumentException;

final readonly class PrizeRuleSet
{
    /**
     * @param  list<PrizeTier>  $tiers
     */
    public function __construct(
        public string $version,
        public string $currency,
        public array $tiers,
    ) {
        if (trim($version) === '') {
            throw new InvalidArgumentException('The prize rule version cannot be empty.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('The prize currency must be an ISO 4217 code.');
        }

        if ($tiers === []) {
            throw new InvalidArgumentException('A prize rule set must contain at least one tier.');
        }

        $previousMinimum = -1;
        $previousPool = -1;

        foreach ($tiers as $tier) {
            if (! $tier instanceof PrizeTier) {
                throw new InvalidArgumentException('Every configured prize tier must be a PrizeTier.');
            }

            if ($tier->minimumSquares <= $previousMinimum) {
                throw new InvalidArgumentException('Prize tiers must have unique, ascending production limits.');
            }

            if ($tier->poolCents < $previousPool) {
                throw new InvalidArgumentException('Prize pools cannot decrease as production increases.');
            }

            $previousMinimum = $tier->minimumSquares;
            $previousPool = $tier->poolCents;
        }
    }

    public function tierFor(float $squares): ?PrizeTier
    {
        if (! is_finite($squares) || $squares < 0) {
            throw new InvalidArgumentException('Production must be a finite, non-negative number.');
        }

        $matchedTier = null;

        foreach ($this->tiers as $tier) {
            if ($squares < $tier->minimumSquares) {
                break;
            }

            $matchedTier = $tier;
        }

        return $matchedTier;
    }
}
