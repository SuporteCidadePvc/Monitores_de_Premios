<?php

namespace Tests\Unit;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementAttachment;
use App\Domain\Announcements\AnnouncementScope;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AnnouncementTest extends TestCase
{
    private DateTimeZone $timezone;

    protected function setUp(): void
    {
        $this->timezone = new DateTimeZone('Europe/Lisbon');
    }

    public function test_global_announcement_has_no_sector_and_is_visible_inside_its_window(): void
    {
        $announcement = new Announcement(
            id: 1,
            scope: AnnouncementScope::Global,
            sectorKey: null,
            title: 'Comunicado geral',
            body: 'Mensagem para todos.',
            startsAt: new DateTimeImmutable('2026-08-31 10:00:00', $this->timezone),
            endsAt: new DateTimeImmutable('2026-08-31 12:00:00', $this->timezone),
            isActive: true,
        );

        $this->assertTrue($announcement->isVisibleAt(new DateTimeImmutable('2026-08-31 11:00:00', $this->timezone)));
        $this->assertFalse($announcement->isVisibleAt(new DateTimeImmutable('2026-08-31 12:00:00', $this->timezone)));
    }

    public function test_scope_must_match_its_sector(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Announcement(
            id: 1,
            scope: AnnouncementScope::Pvc,
            sectorKey: 'aluminio',
            title: 'Inválido',
            body: 'Não deve ser aceite.',
            startsAt: new DateTimeImmutable('2026-08-31 10:00:00', $this->timezone),
            endsAt: null,
            isActive: true,
        );
    }

    public function test_an_attachment_can_replace_the_body(): void
    {
        $announcement = $this->announcement(body: '', attachment: $this->imageAttachment());

        $this->assertSame('', $announcement->body);
        $this->assertTrue($announcement->attachment?->isImage());
    }

    public function test_an_announcement_without_body_requires_an_attachment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An announcement must have a body or an attachment.');

        $this->announcement(body: '   ', attachment: null);
    }

    public function test_a_text_announcement_has_no_attachment_by_default(): void
    {
        $this->assertNull($this->announcement(body: 'Mensagem.', attachment: null)->attachment);
    }

    private function announcement(string $body, ?AnnouncementAttachment $attachment): Announcement
    {
        return new Announcement(
            id: 1,
            scope: AnnouncementScope::Global,
            sectorKey: null,
            title: 'Comunicado geral',
            body: $body,
            startsAt: new DateTimeImmutable('2026-08-31 10:00:00', $this->timezone),
            endsAt: null,
            isActive: true,
            attachment: $attachment,
        );
    }

    private function imageAttachment(): AnnouncementAttachment
    {
        return new AnnouncementAttachment(
            id: 1,
            storedName: '0b6f2f5e-7c1a-4d7e-9a51-3f8d2c1b4e6a.png',
            originalName: 'cartaz.png',
            mimeType: 'image/png',
            sizeBytes: 2048,
            sha256: str_repeat('a', 64),
            width: 1920,
            height: 1080,
        );
    }
}
