<?php

namespace App\Domain\Announcements;

use App\Domain\Display\SectorContext;
use DateTimeImmutable;

interface AnnouncementRepository
{
    public function activeFor(SectorContext $sector, DateTimeImmutable $at): ?Announcement;

    /**
     * Attachment of an announcement that is active and inside its display window at $at.
     */
    public function visibleAttachment(int $announcementId, DateTimeImmutable $at): ?AnnouncementAttachment;
}
