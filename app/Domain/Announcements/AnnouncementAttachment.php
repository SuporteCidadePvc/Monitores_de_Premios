<?php

namespace App\Domain\Announcements;

use InvalidArgumentException;

final readonly class AnnouncementAttachment
{
    public const IMAGE_MIME_TYPES = AnnouncementFile::IMAGE_MIME_TYPES;

    public function __construct(
        public int $id,
        public string $storedName,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public ?int $width,
        public ?int $height,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException('An announcement attachment must have an identifier.');
        }

        // Shares the file metadata rules with files that are not yet linked to a row.
        new AnnouncementFile($storedName, $originalName, $mimeType, $sizeBytes, $sha256, $width, $height);
    }

    public function isImage(): bool
    {
        return in_array($this->mimeType, self::IMAGE_MIME_TYPES, true);
    }
}
