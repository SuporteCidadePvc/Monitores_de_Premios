<?php

namespace App\Domain\Announcements;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Announcement
{
    public function __construct(
        public int $id,
        public AnnouncementScope $scope,
        public ?string $sectorKey,
        public string $title,
        public string $body,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public bool $isActive,
        public ?AnnouncementAttachment $attachment = null,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException('An announcement must have an identifier.');
        }

        if (trim($title) === '' || mb_strlen($title) > 160) {
            throw new InvalidArgumentException('An announcement title must contain between 1 and 160 characters.');
        }

        if (trim($body) === '' && $attachment === null) {
            throw new InvalidArgumentException('An announcement must have a body or an attachment.');
        }

        if ($scope === AnnouncementScope::Global && $sectorKey !== null) {
            throw new InvalidArgumentException('A global announcement cannot have a sector.');
        }

        if ($scope !== AnnouncementScope::Global && $sectorKey !== $scope->value) {
            throw new InvalidArgumentException('A sector announcement must match its scope.');
        }

        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('The announcement end must be after its start.');
        }
    }

    public function isVisibleAt(DateTimeImmutable $at): bool
    {
        return $this->isActive
            && $this->startsAt <= $at
            && ($this->endsAt === null || $this->endsAt > $at);
    }
}
