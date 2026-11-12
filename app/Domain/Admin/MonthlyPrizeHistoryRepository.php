<?php

namespace App\Domain\Admin;

interface MonthlyPrizeHistoryRepository
{
    public function search(MonthlyPrizeHistoryQuery $query): MonthlyPrizeHistoryPage;

    /** @return list<MonthlyPrizeHistorySummary> */
    public function summaries(MonthlyPrizeHistoryQuery $query): array;

    /** @return iterable<MonthlyPrizeHistoryRecord> */
    public function exportRecords(MonthlyPrizeHistoryQuery $query): iterable;
}
