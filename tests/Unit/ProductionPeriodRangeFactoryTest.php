<?php

namespace Tests\Unit;

use App\Domain\Production\ProductionPeriod;
use App\Domain\Production\ProductionPeriodRangeFactory;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class ProductionPeriodRangeFactoryTest extends TestCase
{
    private ProductionPeriodRangeFactory $factory;

    private DateTimeImmutable $reference;

    protected function setUp(): void
    {
        $this->factory = new ProductionPeriodRangeFactory('Europe/Lisbon');
        $this->reference = new DateTimeImmutable('2026-08-31 15:45:00', new DateTimeZone('Europe/Lisbon'));
    }

    public function test_today_uses_a_half_open_local_day(): void
    {
        $range = $this->factory->for(ProductionPeriod::Today, $this->reference);

        $this->assertSame('2026-08-31 00:00:00', $range->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 00:00:00', $range->endExclusive->format('Y-m-d H:i:s'));
    }

    public function test_week_uses_monday_as_its_first_day(): void
    {
        $range = $this->factory->for(ProductionPeriod::Week, $this->reference);

        $this->assertSame('2026-08-31 00:00:00', $range->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-07 00:00:00', $range->endExclusive->format('Y-m-d H:i:s'));
    }

    public function test_month_uses_the_first_day_of_adjacent_months(): void
    {
        $range = $this->factory->for(ProductionPeriod::Month, $this->reference);

        $this->assertSame('2026-08-01 00:00:00', $range->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 00:00:00', $range->endExclusive->format('Y-m-d H:i:s'));
    }
}
