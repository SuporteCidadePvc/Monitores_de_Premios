<?php

namespace App\Domain\Prizes;

use App\Domain\Admin\AdminUser;
use App\Domain\Prizes\Exceptions\DuplicateMonthlyPrize;

final readonly class MonthlyPrizeService
{
    public function __construct(
        private PrizeRuleProvider $ruleProvider,
        private PrizeCalculator $calculator,
        private MonthlyPrizeRepository $repository,
    ) {}

    public function preview(PrizeCalculationInput $input): PrizeCalculationResult
    {
        return $this->calculator->calculate(
            $this->ruleProvider->forSector($input->sectorKey),
            $input,
        );
    }

    /**
     * @throws DuplicateMonthlyPrize
     */
    public function record(PrizeCalculationInput $input, AdminUser $actor): PrizeCalculationResult
    {
        if ($this->repository->find($input->sectorKey, $input->period) !== null) {
            throw DuplicateMonthlyPrize::for($input->sectorKey, $input->period);
        }

        $result = $this->preview($input);

        $this->repository->add($result, $actor);

        return $result;
    }
}
