<?php

namespace App\Domain\Prizes;

final class PrizeCalculator
{
    public function selectPool(PrizeRuleSet $ruleSet, float $squares): PrizePoolResult
    {
        $tier = $ruleSet->tierFor($squares);

        return new PrizePoolResult(
            ruleVersion: $ruleSet->version,
            currency: $ruleSet->currency,
            minimumSquares: $tier?->minimumSquares,
            poolCents: $tier?->poolCents ?? 0,
        );
    }

    public function calculate(PrizeRuleSet $ruleSet, PrizeCalculationInput $input): PrizeCalculationResult
    {
        $pool = $this->selectPool($ruleSet, $input->squares);
        $numerator = $pool->poolCents * $input->averageHoursPerEmployee->hundredths;
        $averagePrizeCents = intdiv(
            $numerator + intdiv($input->totalHours->hundredths, 2),
            $input->totalHours->hundredths,
        );

        return new PrizeCalculationResult(
            sectorKey: $input->sectorKey,
            period: $input->period,
            squares: $input->squares,
            totalHoursHundredths: $input->totalHours->hundredths,
            averageHoursHundredths: $input->averageHoursPerEmployee->hundredths,
            ruleVersion: $pool->ruleVersion,
            currency: $pool->currency,
            minimumSquares: $pool->minimumSquares,
            poolCents: $pool->poolCents,
            averagePrizeCents: $averagePrizeCents,
        );
    }
}
