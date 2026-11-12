<?php

namespace App\Domain\Prizes;

use App\Domain\Admin\AdminUser;
use App\Domain\Prizes\Exceptions\DuplicateMonthlyPrize;

interface MonthlyPrizeRepository
{
    public function find(string $sectorKey, PrizeMonth $period): ?PrizeCalculationResult;

    /**
     * The implementation must atomically enforce uniqueness by sector and month.
     *
     * @throws DuplicateMonthlyPrize
     */
    public function add(PrizeCalculationResult $result, AdminUser $actor): void;
}
