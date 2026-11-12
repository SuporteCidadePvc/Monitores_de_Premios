<?php

namespace Tests\Fakes;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementAttachment;
use App\Domain\Announcements\AnnouncementRepository;
use App\Domain\Announcements\Exceptions\AnnouncementDataUnavailable;
use App\Domain\Display\SectorContext;
use DateTimeImmutable;

final class FakeAnnouncementRepository implements AnnouncementRepository
{
    /** @var list<string> */
    public array $sectors = [];

    public ?Announcement $announcement = null;

    public bool $shouldFail = false;

    public function activeFor(SectorContext $sector, DateTimeImmutable $at): ?Announcement
    {
        $this->sectors[] = $sector->key;

        if ($this->shouldFail) {
            throw AnnouncementDataUnavailable::duringQuery();
        }

        if (! $this->announcement instanceof Announcement
            || ! $this->announcement->isVisibleAt($at)
            || ! $this->announcement->scope->appliesTo($sector)) {
            return null;
        }

        return $this->announcement;
    }

    public function visibleAttachment(int $announcementId, DateTimeImmutable $at): ?AnnouncementAttachment
    {
        if ($this->shouldFail) {
            throw AnnouncementDataUnavailable::duringQuery();
        }

        if (! $this->announcement instanceof Announcement
            || $this->announcement->id !== $announcementId
            || ! $this->announcement->isVisibleAt($at)) {
            return null;
        }

        return $this->announcement->attachment;
    }
}
