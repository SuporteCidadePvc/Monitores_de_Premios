<?php

namespace Tests\Unit;

use App\Domain\Announcements\Exceptions\AnnouncementDataUnavailable;
use App\Domain\Display\SectorContext;
use App\Infrastructure\Announcements\ResilientAnnouncementRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\Fakes\FakeAnnouncementRepository;
use Tests\TestCase;

final class ResilientAnnouncementRepositoryTest extends TestCase
{
    public function test_it_does_not_retry_the_dashboard_source_during_the_backoff_window(): void
    {
        $source = new FakeAnnouncementRepository;
        $source->shouldFail = true;
        $repository = new ResilientAnnouncementRepository($source, Cache::store('array'), 60);
        $sector = $this->sector();
        $at = new DateTimeImmutable('2026-09-11 10:53:00');

        try {
            $repository->activeFor($sector, $at);
            $this->fail('The first unavailable source read must be reported.');
        } catch (AnnouncementDataUnavailable $exception) {
            $this->assertSame('query', $exception->stage);
        }

        try {
            $repository->activeFor($sector, $at);
            $this->fail('The retry backoff must still report the source as unavailable.');
        } catch (AnnouncementDataUnavailable $exception) {
            $this->assertSame('backoff', $exception->stage);
        }

        $this->assertCount(1, $source->sectors);
    }

    public function test_attachment_reads_share_the_same_backoff_window(): void
    {
        $source = new FakeAnnouncementRepository;
        $source->shouldFail = true;
        $repository = new ResilientAnnouncementRepository($source, Cache::store('array'), 60);
        $at = new DateTimeImmutable('2026-09-11 10:53:00');

        try {
            $repository->visibleAttachment(5, $at);
            $this->fail('The first unavailable source read must be reported.');
        } catch (AnnouncementDataUnavailable $exception) {
            $this->assertSame('query', $exception->stage);
        }

        $source->shouldFail = false;

        try {
            $repository->activeFor($this->sector(), $at);
            $this->fail('The display must not retry the source during the backoff window.');
        } catch (AnnouncementDataUnavailable $exception) {
            $this->assertSame('backoff', $exception->stage);
        }
    }

    private function sector(): SectorContext
    {
        $configuration = config('sectors.sectors.pvc');

        $this->assertIsArray($configuration);

        return SectorContext::fromConfiguration('pvc', $configuration);
    }
}
