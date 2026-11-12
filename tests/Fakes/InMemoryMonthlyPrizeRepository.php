<?php

namespace Tests\Fakes;

use App\Domain\Admin\AdminUser;
use App\Domain\Prizes\Exceptions\DuplicateMonthlyPrize;
use App\Domain\Prizes\MonthlyPrizeRepository;
use App\Domain\Prizes\PrizeCalculationResult;
use App\Domain\Prizes\PrizeMonth;

final class InMemoryMonthlyPrizeRepository implements MonthlyPrizeRepository
{
    /** @var array<string, PrizeCalculationResult> */
    private array $records = [];

    public ?PrizeCalculationResult $lastAdded = null;

    public ?AdminUser $lastActor = null;

    public function find(string $sectorKey, PrizeMonth $period): ?PrizeCalculationResult
    {
        return $this->records[$this->key($sectorKey, $period)] ?? null;
    }

    public function add(PrizeCalculationResult $result, AdminUser $actor): void
    {
        $key = $this->key($result->sectorKey, $result->period);

        if (isset($this->records[$key])) {
            throw DuplicateMonthlyPrize::for($result->sectorKey, $result->period);
        }

        $this->records[$key] = $result;
        $this->lastAdded = $result;
        $this->lastActor = $actor;
    }

    private function key(string $sectorKey, PrizeMonth $period): string
    {
        return sprintf('%s:%04d-%02d', $sectorKey, $period->year, $period->month);
    }
}
