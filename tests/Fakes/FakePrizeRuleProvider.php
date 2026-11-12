<?php

namespace Tests\Fakes;

use App\Domain\Prizes\Exceptions\PrizeRuleDataUnavailable;
use App\Domain\Prizes\PrizeRuleProvider;
use App\Domain\Prizes\PrizeRuleSet;
use App\Infrastructure\Config\ConfiguredPrizeRuleProvider;

final class FakePrizeRuleProvider implements PrizeRuleProvider
{
    public bool $shouldFail = false;

    private ConfiguredPrizeRuleProvider $configured;

    public function __construct()
    {
        $this->configured = new ConfiguredPrizeRuleProvider((array) config('prizes'));
    }

    public function forSector(string $sectorKey): PrizeRuleSet
    {
        if ($this->shouldFail) {
            throw new PrizeRuleDataUnavailable('The prize rule database is unavailable.');
        }

        return $this->configured->forSector($sectorKey);
    }
}
