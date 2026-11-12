<?php

namespace Tests\Fakes;

use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use App\Domain\Admin\MonthlyPrizeHistoryPage;
use App\Domain\Admin\MonthlyPrizeHistoryQuery;
use App\Domain\Admin\MonthlyPrizeHistoryRecord;
use App\Domain\Admin\MonthlyPrizeHistoryRepository;
use App\Domain\Admin\MonthlyPrizeHistorySummary;

final class FakeMonthlyPrizeHistoryRepository implements MonthlyPrizeHistoryRepository
{
    public ?MonthlyPrizeHistoryQuery $lastQuery = null;

    public ?MonthlyPrizeHistoryPage $page = null;

    public bool $shouldFail = false;

    /** @var list<MonthlyPrizeHistorySummary> */
    public array $summaries = [];

    /** @var list<MonthlyPrizeHistoryRecord> */
    public array $exportRecords = [];

    public function search(MonthlyPrizeHistoryQuery $query): MonthlyPrizeHistoryPage
    {
        $this->lastQuery = $query;

        if ($this->shouldFail) {
            throw new AdminDataUnavailable('The administrative database is unavailable.');
        }

        return $this->page ?? new MonthlyPrizeHistoryPage([], 0, $query->page, $query->perPage);
    }

    public function summaries(MonthlyPrizeHistoryQuery $query): array
    {
        if ($this->shouldFail) {
            throw new AdminDataUnavailable('The administrative database is unavailable.');
        }

        return $this->summaries;
    }

    public function exportRecords(MonthlyPrizeHistoryQuery $query): iterable
    {
        $this->lastQuery = $query;

        if ($this->shouldFail) {
            throw new AdminDataUnavailable('The administrative database is unavailable.');
        }

        return $this->exportRecords;
    }
}
