<?php

namespace App\Domain\Production;

use DateTimeImmutable;
use DateTimeZone;

final readonly class ProductionPeriodRangeFactory
{
    private DateTimeZone $timezone;

    public function __construct(string $timezone)
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function for(ProductionPeriod $period, ?DateTimeImmutable $reference = null): ProductionDateRange
    {
        $localReference = ($reference ?? new DateTimeImmutable('now', $this->timezone))
            ->setTimezone($this->timezone);

        $start = match ($period) {
            ProductionPeriod::Today => $localReference->setTime(0, 0),
            ProductionPeriod::Week => $localReference->modify('monday this week')->setTime(0, 0),
            ProductionPeriod::Month => $localReference->modify('first day of this month')->setTime(0, 0),
        };

        $endExclusive = match ($period) {
            ProductionPeriod::Today => $start->modify('+1 day'),
            ProductionPeriod::Week => $start->modify('+1 week'),
            ProductionPeriod::Month => $start->modify('first day of next month'),
        };

        return new ProductionDateRange($start, $endExclusive);
    }

    public function forCalendarMonth(int $year, int $month): ProductionDateRange
    {
        $start = (new DateTimeImmutable('now', $this->timezone))
            ->setDate($year, $month, 1)
            ->setTime(0, 0);

        return new ProductionDateRange(
            $start,
            $start->modify('first day of next month'),
        );
    }
}
