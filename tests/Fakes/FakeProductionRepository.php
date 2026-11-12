<?php

namespace Tests\Fakes;

use App\Domain\Display\SectorContext;
use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use App\Domain\Production\ProductionDateRange;
use App\Domain\Production\ProductionRepository;
use App\Domain\Production\ProductionSnapshot;
use DateTimeImmutable;

final class FakeProductionRepository implements ProductionRepository
{
    /** @var list<array{sector: string, range: ProductionDateRange}> */
    public array $calls = [];

    public bool $shouldFail = false;

    public float $squares = 1234.0;

    public int $frames = 321;

    public bool $hasData = true;

    public function summarize(SectorContext $sector, ProductionDateRange $range): ProductionSnapshot
    {
        $this->calls[] = [
            'sector' => $sector->key,
            'range' => $range,
        ];

        if ($this->shouldFail) {
            throw ProductionDataUnavailable::duringQuery();
        }

        return new ProductionSnapshot(
            squares: $this->squares,
            frames: $this->frames,
            hasData: $this->hasData,
            range: $range,
            retrievedAt: new DateTimeImmutable('2026-08-31 12:30:00', $range->start->getTimezone()),
        );
    }
}
