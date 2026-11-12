<?php

namespace Tests\Feature;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementAttachment;
use App\Domain\Announcements\AnnouncementScope;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

final class AnnouncementImageScreenTest extends TestCase
{
    public function test_an_image_announcement_fills_the_screen_with_the_image(): void
    {
        $this->announcementRepository->announcement = $this->announcement(body: '', withImage: true);

        $this->get('/pvc/comunicado')
            ->assertOk()
            ->assertSee('data-announcement-media="image"', false)
            ->assertSee('announcement-display--image', false)
            ->assertSee('src="'.route('announcements.attachment.display', 21).'?v=cccccccccccc"', false)
            ->assertSee('alt="Cartaz &quot;Segurança&quot;"', false)
            ->assertSee('width="1920"', false)
            ->assertSee('height="1080"', false)
            ->assertSee('<h1 id="screen-title" class="visually-hidden" data-announcement-title>', false)
            ->assertDontSee('data-announcement-body', false);

        $this->assertCount(0, $this->productionRepository->calls);
    }

    public function test_the_fallback_keeps_the_title_and_the_escaped_message(): void
    {
        $this->announcementRepository->announcement = $this->announcement(body: "<b>Linha 1</b>\nLinha 2", withImage: true);

        $this->get('/pvc/comunicado')
            ->assertOk()
            ->assertSee('data-announcement-image-fallback', false)
            ->assertSee('announcement-image-fallback__title', false)
            ->assertSee('&lt;b&gt;Linha 1&lt;/b&gt;<br />', false)
            ->assertDontSee('<b>Linha 1</b>', false);
    }

    public function test_a_text_announcement_keeps_the_current_screen(): void
    {
        $this->announcementRepository->announcement = $this->announcement(body: 'Mensagem de texto', withImage: false);

        $this->get('/pvc/comunicado')
            ->assertOk()
            ->assertSee('data-announcement-body', false)
            ->assertSee('Mensagem de texto')
            ->assertDontSee('data-announcement-media', false)
            ->assertDontSee('announcement-display--image', false);
    }

    public function test_other_screens_do_not_switch_to_the_image_layout(): void
    {
        $this->announcementRepository->announcement = $this->announcement(body: '', withImage: true);

        $this->get('/pvc/hoje')
            ->assertOk()
            ->assertDontSee('data-announcement-media', false)
            ->assertSee('&quot;screen&quot;:&quot;comunicado&quot;', false);
    }

    private function announcement(string $body, bool $withImage): Announcement
    {
        $timezone = new DateTimeZone('Europe/Lisbon');

        return new Announcement(
            id: 21,
            scope: AnnouncementScope::Global,
            sectorKey: null,
            title: 'Cartaz "Segurança"',
            body: $body,
            startsAt: new DateTimeImmutable('2020-01-01 00:00:00', $timezone),
            endsAt: new DateTimeImmutable('2099-01-01 00:00:00', $timezone),
            isActive: true,
            attachment: $withImage ? new AnnouncementAttachment(
                id: 4,
                storedName: '0b6f2f5e-7c1a-4d7e-9a51-3f8d2c1b4e6a.png',
                originalName: 'cartaz.png',
                mimeType: 'image/png',
                sizeBytes: 4096,
                sha256: str_repeat('c', 64),
                width: 1920,
                height: 1080,
            ) : null,
        );
    }
}
