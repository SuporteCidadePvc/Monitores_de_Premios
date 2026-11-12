<?php

namespace Tests\Integration;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementRepository;
use App\Domain\Display\SectorContext;
use Tests\TestCase;

final class OdbcAnnouncementRepositoryTest extends TestCase
{
    public function test_it_reads_the_active_announcement_from_the_dashboard_dsn(): void
    {
        if (! filter_var(env('RUN_ODBC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('ODBC integration tests are disabled.');
        }

        $configuration = config('sectors.sectors.pvc');

        $this->assertIsArray($configuration);

        $sector = SectorContext::fromConfiguration('pvc', $configuration);
        $announcement = $this->app->make(AnnouncementRepository::class)->activeFor(
            $sector,
            now((string) config('production.timezone'))->toImmutable(),
        );

        $this->assertTrue($announcement === null || $announcement instanceof Announcement);
    }
}
