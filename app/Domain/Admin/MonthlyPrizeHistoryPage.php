<?php

namespace App\Domain\Admin;

final readonly class MonthlyPrizeHistoryPage
{
    /** @param list<MonthlyPrizeHistoryRecord> $records */
    public function __construct(
        public array $records,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
