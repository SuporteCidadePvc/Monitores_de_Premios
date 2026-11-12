<?php

namespace Tests\Unit;

use App\Domain\Announcements\AnnouncementAttachment;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnnouncementAttachmentTest extends TestCase
{
    public function test_a_valid_image_attachment_is_accepted(): void
    {
        $attachment = $this->attachment();

        $this->assertTrue($attachment->isImage());
        $this->assertSame(1920, $attachment->width);
        $this->assertSame(1080, $attachment->height);
    }

    #[DataProvider('supportedMimeTypes')]
    public function test_supported_image_types_are_accepted(string $mimeType): void
    {
        $this->assertSame($mimeType, $this->attachment(mimeType: $mimeType)->mimeType);
    }

    /** @return iterable<string, array{string}> */
    public static function supportedMimeTypes(): iterable
    {
        yield 'jpeg' => ['image/jpeg'];
        yield 'png' => ['image/png'];
        yield 'webp' => ['image/webp'];
    }

    #[DataProvider('invalidAttachments')]
    public function test_invalid_attachments_are_rejected(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->attachment(...$overrides);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidAttachments(): iterable
    {
        yield 'svg can carry scripts' => [['mimeType' => 'image/svg+xml']];
        yield 'pdf is not in phase 1' => [['mimeType' => 'application/pdf']];
        yield 'forward slash in stored name' => [['storedName' => 'a/b.png']];
        yield 'backslash in stored name' => [['storedName' => 'a\\b.png']];
        yield 'drive separator in stored name' => [['storedName' => 'c:b.png']];
        yield 'parent directory in stored name' => [['storedName' => '..png']];
        yield 'empty stored name' => [['storedName' => '']];
        yield 'stored name longer than the column' => [['storedName' => str_repeat('a', 65)]];
        yield 'empty original name' => [['originalName' => '  ']];
        yield 'zero size' => [['sizeBytes' => 0]];
        yield 'short hash' => [['sha256' => str_repeat('a', 63)]];
        yield 'non hexadecimal hash' => [['sha256' => str_repeat('g', 64)]];
        yield 'width without height' => [['height' => null]];
        yield 'non positive dimensions' => [['width' => 0]];
        yield 'missing identifier' => [['id' => 0]];
    }

    private function attachment(
        int $id = 1,
        string $storedName = '0b6f2f5e-7c1a-4d7e-9a51-3f8d2c1b4e6a.png',
        string $originalName = 'cartaz.png',
        string $mimeType = 'image/png',
        int $sizeBytes = 2048,
        ?string $sha256 = null,
        ?int $width = 1920,
        ?int $height = 1080,
    ): AnnouncementAttachment {
        return new AnnouncementAttachment(
            id: $id,
            storedName: $storedName,
            originalName: $originalName,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            sha256: $sha256 ?? str_repeat('a', 64),
            width: $width,
            height: $height,
        );
    }
}
