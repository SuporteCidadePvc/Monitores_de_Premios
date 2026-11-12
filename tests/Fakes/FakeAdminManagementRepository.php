<?php

namespace Tests\Fakes;

use App\Domain\Admin\AdminManagementRepository;
use App\Domain\Admin\AdminUser;
use App\Domain\Admin\Exceptions\AdminOperationFailed;
use App\Domain\Announcements\AnnouncementAttachment;
use App\Domain\Announcements\AnnouncementFile;

final class FakeAdminManagementRepository implements AdminManagementRepository
{
    /** @var array{rules:list<array<string, mixed>>,announcements:list<array<string, mixed>>,audit:list<array<string, mixed>>} */
    public array $data = ['rules' => [], 'announcements' => [], 'audit' => []];

    /** @var list<string> */
    public array $operations = [];

    /** @var list<string> */
    public array $attachmentNames = [];

    public ?string $unlinkedAttachment = null;

    public bool $failWrites = false;

    /** @var array<int, AnnouncementAttachment> */
    public array $attachments = [];

    public function dashboard(): array
    {
        return $this->data;
    }

    public function createPrizeRule(string $sectorKey, int $minimumSquares, int $prizePoolCents, AdminUser $actor): void
    {
        $this->operations[] = "rule:{$sectorKey}:{$minimumSquares}:{$prizePoolCents}";
    }

    public function updatePrizeRule(int $id, int $minimumSquares, int $prizePoolCents, AdminUser $actor): void
    {
        $this->operations[] = "rule:update:{$id}:{$minimumSquares}:{$prizePoolCents}";
    }

    public function deactivatePrizeRule(int $id, AdminUser $actor): void
    {
        $this->operations[] = "rule:deactivate:{$id}";
    }

    public function deletePrizeRule(int $id, AdminUser $actor): void
    {
        $this->operations[] = "rule:delete:{$id}";
    }

    public function createAnnouncement(string $scope, string $title, string $body, string $startsAt, ?string $endsAt, AdminUser $actor, ?AnnouncementFile $attachment = null): void
    {
        $this->failIfRequested();
        $this->operations[] = "announcement:{$scope}:{$title}".($attachment === null ? '' : ":attachment:{$attachment->storedName}");
    }

    public function updateAnnouncement(int $id, string $scope, string $title, string $body, string $startsAt, ?string $endsAt, AdminUser $actor, ?AnnouncementFile $newAttachment = null, bool $removeAttachment = false): ?string
    {
        $this->failIfRequested();
        $this->operations[] = "announcement:update:{$id}:{$scope}:{$title}"
            .($newAttachment === null ? '' : ":attachment:{$newAttachment->storedName}")
            .($removeAttachment ? ':remove-attachment' : '');

        return $this->unlinkedAttachment;
    }

    public function deactivateAnnouncement(int $id, AdminUser $actor): void
    {
        $this->operations[] = "announcement:deactivate:{$id}";
    }

    public function deleteAnnouncement(int $id, AdminUser $actor): ?string
    {
        $this->failIfRequested();
        $this->operations[] = "announcement:delete:{$id}";

        return $this->unlinkedAttachment;
    }

    public function announcementAttachment(int $announcementId): ?AnnouncementAttachment
    {
        return $this->attachments[$announcementId] ?? null;
    }

    public function announcementAttachmentNames(): array
    {
        return $this->attachmentNames;
    }

    private function failIfRequested(): void
    {
        if ($this->failWrites) {
            throw new AdminOperationFailed('Simulated administrative failure.');
        }
    }
}
