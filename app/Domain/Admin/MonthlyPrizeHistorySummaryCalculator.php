<?php

namespace App\Domain\Admin;

use App\Domain\Prizes\PrizeMonth;

final class MonthlyPrizeHistorySummaryCalculator
{
    /**
     * @param  list<MonthlyPrizeHistoryRecord>  $records
     * @return list<MonthlyPrizeHistorySummary>
     */
    public function calculate(array $records): array
    {
        /** @var array<string, array{count:int,production_total:float,maximum_production:float,best_production_periods:list<PrizeMonth>,maximum_average_prize:int,best_average_prize_periods:list<PrizeMonth>}> $sectors */
        $sectors = [];

        foreach ($records as $record) {
            $period = new PrizeMonth($record->year, $record->month);

            if (! isset($sectors[$record->sectorKey])) {
                $sectors[$record->sectorKey] = [
                    'count' => 0,
                    'production_total' => 0.0,
                    'maximum_production' => $record->productionSquares,
                    'best_production_periods' => [],
                    'maximum_average_prize' => $record->averagePrizeCents,
                    'best_average_prize_periods' => [],
                ];
            }

            $summary = &$sectors[$record->sectorKey];
            $summary['count']++;
            $summary['production_total'] += $record->productionSquares;

            if ($record->productionSquares > $summary['maximum_production']) {
                $summary['maximum_production'] = $record->productionSquares;
                $summary['best_production_periods'] = [$period];
            } elseif (abs($record->productionSquares - $summary['maximum_production']) < 0.00001) {
                $summary['best_production_periods'][] = $period;
            }

            if ($record->averagePrizeCents > $summary['maximum_average_prize']) {
                $summary['maximum_average_prize'] = $record->averagePrizeCents;
                $summary['best_average_prize_periods'] = [$period];
            } elseif ($record->averagePrizeCents === $summary['maximum_average_prize']) {
                $summary['best_average_prize_periods'][] = $period;
            }

            unset($summary);
        }

        ksort($sectors);

        return array_map(
            static fn (array $summary, string $sectorKey): MonthlyPrizeHistorySummary => new MonthlyPrizeHistorySummary(
                sectorKey: $sectorKey,
                recordCount: $summary['count'],
                averageProductionSquares: $summary['production_total'] / $summary['count'],
                maximumProductionSquares: $summary['maximum_production'],
                bestProductionPeriods: $summary['best_production_periods'],
                maximumAveragePrizeCents: $summary['maximum_average_prize'],
                bestAveragePrizePeriods: $summary['best_average_prize_periods'],
            ),
            $sectors,
            array_keys($sectors),
        );
    }
}
