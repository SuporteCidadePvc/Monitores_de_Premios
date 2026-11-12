<?php

namespace App\Domain\Announcements\Exceptions;

use RuntimeException;
use Throwable;

final class AnnouncementAttachmentRejected extends RuntimeException
{
    public const UPLOAD_FAILED = 'upload_failed';

    public const TOO_LARGE = 'too_large';

    public const UNSUPPORTED_TYPE = 'unsupported_type';

    public const NOT_AN_IMAGE = 'not_an_image';

    public const STORAGE_FAILED = 'storage_failed';

    private function __construct(public readonly string $reason, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function uploadFailed(): self
    {
        return new self(self::UPLOAD_FAILED, 'The attachment upload did not complete.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self(self::TOO_LARGE, sprintf('The attachment exceeds the %d byte limit.', $maxBytes));
    }

    public static function unsupportedType(string $mimeType): self
    {
        return new self(self::UNSUPPORTED_TYPE, sprintf('The attachment type [%s] is not supported.', $mimeType));
    }

    public static function notAnImage(): self
    {
        return new self(self::NOT_AN_IMAGE, 'The attachment content is not a readable image.');
    }

    public static function storageFailed(?Throwable $previous = null): self
    {
        return new self(self::STORAGE_FAILED, 'The attachment could not be written to storage.', $previous);
    }
}
