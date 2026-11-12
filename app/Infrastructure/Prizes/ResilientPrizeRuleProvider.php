<?php

namespace App\Infrastructure\Prizes;

use App\Domain\Prizes\Exceptions\PrizeRuleDataUnavailable;
use App\Domain\Prizes\PrizeRuleProvider;
use App\Domain\Prizes\PrizeRuleSet;

final readonly class ResilientPrizeRuleProvider implements PrizeRuleProvider
{
    public function __construct(
        private PrizeRuleProvider $databaseRules,
        private PrizeRuleProvider $fallbackRules,
    ) {}

    public function forSector(string $sectorKey): PrizeRuleSet
    {
        try {
            return $this->databaseRules->forSector($sectorKey);
        } catch (PrizeRuleDataUnavailable) {
            return $this->fallbackRules->forSector($sectorKey);
        }
    }
}
