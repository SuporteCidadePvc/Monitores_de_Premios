<?php

namespace Tests\Feature;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementAttachment;
use App\Domain\Announcements\AnnouncementFile;
use App\Domain\Announcements\AnnouncementScope;
use App\Infrastructure\Announcements\AnnouncementAttachmentStore;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AnnouncementAttachmentDisplayRouteTest extends TestCase
{
    private AnnouncementFile $file;

    protected function setUp(): void
    {
        parent::setUp();

        $store = new AnnouncementAttachmentStore(Storage::fake('local'), 'announcements', 10 * 1024 * 1024);
        $this->app->instance(AnnouncementAttachmentStore::class, $store);
        $this->file = $store->store(UploadedFile::fake()->image('cartaz.png', 32, 18));
    }

    public function test_the_image_of_a_visible_announcement_is_served_publicly_with_cache_headers(): void
    {
        $this->announcementRepository->announcement = $this->announcement(isActive: true, endsAt: '2099-01-01 00:00:00');

        $response = $this->get(route('announcements.attachment.display', 5));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('"'.$this->file->sha256.'"', $response->headers->get('ETag'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_a_matching_etag_returns_not_modified(): void
    {
        $this->announcementRepository->announcement = $this->announcement(isActive: true, endsAt: null);

        $this->get(route('announcements.attachment.display', 5), ['If-None-Match' => '"'.$this->file->sha256.'"'])
            ->assertStatus(304);
    }

    public function test_inactive_expired_or_unknown_announcements_are_not_served(): void
    {
        $this->announcementRepository->announcement = $this->announcement(isActive: false, endsAt: null);
        $this->get(route('announcements.attachment.display', 5))->assertNotFound();

        $this->announcementRepository->announcement = $this->announcement(isActive: true, endsAt: '2020-01-02 00:00:00');
        $this->get(route('announcements.attachment.display', 5))->assertNotFound();

        $this->announcementRepository->announcement = $this->announcement(isActive: true, endsAt: null);
        $this->get(route('announcements.attachment.display', 6))->assertNotFound();
    }

    public function test_a_missing_file_on_disk_is_not_found(): void
    {
        $this->announcementRepository->announcement = $this->announcement(isActive: true, endsAt: null);
        Storage::disk('local')->delete('announcements/'.$this->file->storedName);

        $this->get(route('announcements.attachment.display', 5))->assertNotFound();
    }

    public function test_an_unavailable_database_returns_service_unavailable(): void
    {
        $this->announcementRepository->shouldFail = true;

        $this->get(route('announcements.attachment.display', 5))->assertStatus(503);
    }

    private function announcement(bool $isActive, ?string $endsAt): Announcement
    {
        $timezone = new DateTimeZone('Europe/Lisbon');

        return new Announcement(
            id: 5,
            scope: AnnouncementScope::Global,
            sectorKey: null,
            title: 'Cartaz',
            body: '',
            startsAt: new DateTimeImmutable('2020-01-01 00:00:00', $timezone),
            endsAt: $endsAt === null ? null : new DateTimeImmutable($endsAt, $timezone),
            isActive: $isActive,
            attachment: new AnnouncementAttachment(
                id: 1,
                storedName: $this->file->storedName,
                originalName: $this->file->originalName,
                mimeType: $this->file->mimeType,
                sizeBytes: $this->file->sizeBytes,
                sha256: $this->file->sha256,
                width: $this->file->width,
                height: $this->file->height,
            ),
        );
    }
}
