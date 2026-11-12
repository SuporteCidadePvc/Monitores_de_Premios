<?php

namespace Tests\Unit;

use App\Domain\Admin\MonthlyPrizeHistoryRecord;
use App\Domain\Admin\MonthlyPrizeHistorySummaryCalculator;
use Tests\TestCase;

final class MonthlyPrizeHistorySummaryCalculatorTest extends TestCase
{
    public function test_it_calculates_each_sector_independently_and_preserves_ties(): void
    {
        $summaries = (new MonthlyPrizeHistorySummaryCalculator)->calculate([
            $this->record('pvc', 2026, 1, 100.0, 1000),
            $this->record('pvc', 2026, 2, 200.0, 1000),
            $this->record('pvc', 2026, 3, 200.0, 3000),
            $this->record('aluminio', 2026, 1, 80.0, 4500),
        ]);

        $this->assertCount(2, $summaries);
        $this->assertSame('aluminio', $summaries[0]->sectorKey);
        $this->assertSame(80.0, $summaries[0]->maximumProductionSquares);
        $this->assertSame('pvc', $summaries[1]->sectorKey);
        $this->assertSame(3, $summaries[1]->recordCount);
        $this->assertEqualsWithDelta(166.666, $summaries[1]->averageProductionSquares, 0.001);
        $this->assertSame(200.0, $summaries[1]->maximumProductionSquares);
        $this->assertSame([2, 3], array_map(static fn ($period): int => $period->month, $summaries[1]->bestProductionPeriods));
        $this->assertSame(3000, $summaries[1]->maximumAveragePrizeCents);
        $this->assertSame([3], array_map(static fn ($period): int => $period->month, $summaries[1]->bestAveragePrizePeriods));
    }

    private function record(string $sector, int $year, int $month, float $production, int $averagePrizeCents): MonthlyPrizeHistoryRecord
    {
        return new MonthlyPrizeHistoryRecord(1, $sector, $year, $month, $production, 0, 0, 'premio-producao-v1', 0, $averagePrizeCents, 'EUR', '2026-01-01 00:00:00');
    }
}
