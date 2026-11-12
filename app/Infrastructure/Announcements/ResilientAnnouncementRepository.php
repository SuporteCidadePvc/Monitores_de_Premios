<?php

namespace App\Infrastructure\Announcements;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementAttachment;
use App\Domain\Announcements\AnnouncementRepository;
use App\Domain\Announcements\Exceptions\AnnouncementDataUnavailable;
use App\Domain\Display\SectorContext;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

final readonly class ResilientAnnouncementRepository implements AnnouncementRepository
{
    public function __construct(
        private AnnouncementRepository $source,
        private Repository $cache,
        private int $retryAfterFailureSeconds,
    ) {}

    public function activeFor(SectorContext $sector, DateTimeImmutable $at): ?Announcement
    {
        $backoff = $this->backoffState();

        if ($backoff !== null) {
            throw AnnouncementDataUnavailable::duringBackoff($backoff['odbc_sqlstate']);
        }

        try {
            return $this->source->activeFor($sector, $at);
        } catch (AnnouncementDataUnavailable $exception) {
            $this->startBackoff($exception);

            throw $exception;
        }
    }

    public function visibleAttachment(int $announcementId, DateTimeImmutable $at): ?AnnouncementAttachment
    {
        $backoff = $this->backoffState();

        if ($backoff !== null) {
            throw AnnouncementDataUnavailable::duringBackoff($backoff['odbc_sqlstate']);
        }

        try {
            return $this->source->visibleAttachment($announcementId, $at);
        } catch (AnnouncementDataUnavailable $exception) {
            $this->startBackoff($exception);

            throw $exception;
        }
    }

    /** @return array{odbc_sqlstate:?string}|null */
    private function backoffState(): ?array
    {
        try {
            $state = $this->cache->get('monitor-odbc:dashboard:retry-after-failure');

            if (! is_array($state)) {
                return null;
            }

            $sqlState = $state['odbc_sqlstate'] ?? null;

            return [
                'odbc_sqlstate' => is_string($sqlState) && preg_match('/^[A-Z0-9]{5}$/', $sqlState) === 1
                    ? $sqlState
                    : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function startBackoff(AnnouncementDataUnavailable $exception): void
    {
        try {
            $this->cache->put('monitor-odbc:dashboard:retry-after-failure', [
                'odbc_sqlstate' => $exception->odbcSqlState,
            ], $this->retryAfterFailureSeconds);
        } catch (Throwable) {
            // A cache failure must not suppress future source retries.
        }
    }
}
