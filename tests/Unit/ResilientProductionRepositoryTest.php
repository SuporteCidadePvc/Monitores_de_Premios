<?php

namespace Tests\Unit;

use App\Domain\Display\SectorContext;
use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use App\Domain\Production\ProductionDateRange;
use App\Infrastructure\Cache\FileProductionSnapshotStore;
use App\Infrastructure\Production\ResilientProductionRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Tests\Fakes\FakeProductionRepository;
use Tests\TestCase;

final class ResilientProductionRepositoryTest extends TestCase
{
    public function test_it_uses_the_last_valid_snapshot_when_the_source_becomes_unavailable(): void
    {
        $source = new FakeProductionRepository;
        $repository = $this->repository($source);
        $sector = $this->sector();
        $range = $this->range();

        $fresh = $repository->summarize($sector, $range);
        $source->shouldFail = true;
        $cached = $repository->summarize($sector, $range);

        $this->assertFalse($fresh->isCached);
        $this->assertTrue($cached->isCached);
        $this->assertSame($fresh->squares, $cached->squares);
    }

    public function test_it_does_not_retry_the_production_source_during_the_shared_backoff_window(): void
    {
        $source = new FakeProductionRepository;
        $source->shouldFail = true;
        $repository = $this->repository($source);

        try {
            $repository->summarize($this->sector(), $this->range());
            $this->fail('The first unavailable source read must be reported.');
        } catch (ProductionDataUnavailable $exception) {
            $this->assertSame('query', $exception->stage);
        }

        try {
            $repository->summarize($this->sector(), $this->range());
            $this->fail('The retry backoff must still report the source as unavailable.');
        } catch (ProductionDataUnavailable $exception) {
            $this->assertSame('backoff', $exception->stage);
        }

        $this->assertCount(1, $source->calls);
    }

    public function test_it_keeps_the_source_failure_when_there_is_no_cached_snapshot(): void
    {
        $source = new FakeProductionRepository;
        $source->shouldFail = true;

        $this->expectException(ProductionDataUnavailable::class);
        $this->repository($source)->summarize($this->sector(), $this->range());
    }

    private function repository(FakeProductionRepository $source): ResilientProductionRepository
    {
        $cache = Cache::store('array');

        return new ResilientProductionRepository(
            $source,
            new FileProductionSnapshotStore($cache),
            $cache,
            2,
            0,
            60,
        );
    }

    private function sector(): SectorContext
    {
        $configuration = config('sectors.sectors.pvc');

        $this->assertIsArray($configuration);

        return SectorContext::fromConfiguration('pvc', $configuration);
    }

    private function range(): ProductionDateRange
    {
        $timezone = new DateTimeZone('Europe/Lisbon');

        return new ProductionDateRange(
            new DateTimeImmutable('2026-08-31 00:00:00', $timezone),
            new DateTimeImmutable('2026-09-01 00:00:00', $timezone),
        );
    }
}
