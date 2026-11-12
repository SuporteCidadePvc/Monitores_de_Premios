<?php

namespace App\Domain\Prizes;

interface PrizeRuleProvider
{
    public function forSector(string $sectorKey): PrizeRuleSet;
}
