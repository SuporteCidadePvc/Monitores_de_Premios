<?php

namespace App\Domain\Prizes;

use App\Domain\Display\SectorContext;
use App\Domain\Prizes\Exceptions\MonthlyPrizeClosingNotAllowed;
use App\Domain\Production\ProductionPeriodRangeFactory;
use App\Domain\Production\ProductionRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

final readonly class MonthlyProductionLookup
{
    private DateTimeZone $timezone;

    /** @var Closure(DateTimeZone): DateTimeImmutable */
    private Closure $clock;

    public function __construct(
        private ProductionRepository $productionRepository,
        private ProductionPeriodRangeFactory $rangeFactory,
        string $timezone,
        ?Closure $clock = null,
    ) {
        $this->timezone = new DateTimeZone($timezone);
        $this->clock = $clock ?? static fn (DateTimeZone $timezone): DateTimeImmutable => new DateTimeImmutable('now', $timezone);
    }

    public function squares(SectorContext $sector, PrizeMonth $period, ?DateTimeImmutable $reference = null): float
    {
        $this->ensureMonthIsClosed($period, $reference);

        $snapshot = $this->productionRepository->summarize(
            $sector,
            $this->rangeFactory->forCalendarMonth($period->year, $period->month),
        );

        if (! $snapshot->hasData) {
            throw MonthlyPrizeClosingNotAllowed::productionHasNoRecords($period);
        }

        return $snapshot->squares;
    }

    private function ensureMonthIsClosed(PrizeMonth $period, ?DateTimeImmutable $reference): void
    {
        $now = ($reference ?? ($this->clock)($this->timezone))->setTimezone($this->timezone);
        $currentPeriod = ($now->format('Y') * 100) + (int) $now->format('m');
        $requestedPeriod = ($period->year * 100) + $period->month;

        if ($requestedPeriod >= $currentPeriod) {
            throw MonthlyPrizeClosingNotAllowed::monthIsNotClosed($period);
        }
    }
}
