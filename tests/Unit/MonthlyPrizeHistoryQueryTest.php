<?php

namespace Tests\Unit;

use App\Domain\Admin\MonthlyPrizeHistoryQuery;
use App\Domain\Prizes\PrizeMonth;
use InvalidArgumentException;
use Tests\TestCase;

final class MonthlyPrizeHistoryQueryTest extends TestCase
{
    public function test_it_calculates_a_zero_based_offset_for_a_paginated_history_query(): void
    {
        $query = new MonthlyPrizeHistoryQuery(page: 3, perPage: 12);

        $this->assertSame(24, $query->offset());
    }

    public function test_it_exposes_month_period_numbers_for_database_filtering(): void
    {
        $query = new MonthlyPrizeHistoryQuery(
            from: new PrizeMonth(2025, 11),
            to: new PrizeMonth(2026, 2),
        );

        $this->assertSame(202511, $query->fromPeriodNumber());
        $this->assertSame(202602, $query->toPeriodNumber());
    }

    public function test_it_rejects_an_unknown_sector(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MonthlyPrizeHistoryQuery(sectorKey: 'madeira');
    }

    public function test_it_rejects_an_inverted_period_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MonthlyPrizeHistoryQuery(
            from: new PrizeMonth(2026, 2),
            to: new PrizeMonth(2026, 1),
        );
    }
}
