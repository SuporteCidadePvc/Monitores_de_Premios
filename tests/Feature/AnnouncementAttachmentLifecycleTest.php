<?php

namespace Tests\Feature;

use App\Domain\Admin\AdminUser;
use App\Infrastructure\Announcements\AnnouncementAttachmentStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AnnouncementAttachmentLifecycleTest extends TestCase
{
    private AdminUser $user;

    private Filesystem $disk;

    private AnnouncementAttachmentStore $attachments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new AdminUser(11, 'administrador', 'Administração', Hash::make('uma-password-segura'), true);
        $this->adminUserRepository->add($this->user);

        $this->disk = Storage::fake('local');
        $this->attachments = new AnnouncementAttachmentStore($this->disk, 'announcements', 10 * 1024 * 1024);
        $this->app->instance(AnnouncementAttachmentStore::class, $this->attachments);
    }

    public function test_removing_an_announcement_deletes_its_file_after_the_commit(): void
    {
        $file = $this->attachments->store(UploadedFile::fake()->image('cartaz.png', 10, 10));
        $this->adminManagementRepository->unlinkedAttachment = $file->storedName;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.announcements.delete', 7), ['confirm_delete' => '1'])
            ->assertSessionHas('status');

        $this->assertFalse($this->disk->exists('announcements/'.$file->storedName));
    }

    public function test_a_failed_removal_keeps_the_file(): void
    {
        $file = $this->attachments->store(UploadedFile::fake()->image('cartaz.png', 10, 10));
        $this->adminManagementRepository->unlinkedAttachment = $file->storedName;
        $this->adminManagementRepository->failWrites = true;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.announcements.delete', 7), ['confirm_delete' => '1'])
            ->assertSessionHasErrors('announcements');

        $this->assertTrue($this->disk->exists('announcements/'.$file->storedName));
    }

    public function test_an_update_that_unlinks_an_attachment_deletes_the_old_file(): void
    {
        $file = $this->attachments->store(UploadedFile::fake()->image('antigo.png', 10, 10));
        $this->adminManagementRepository->unlinkedAttachment = $file->storedName;

        $this->actingAs($this->user, 'admin')
            ->post(route('admin.announcements.update', 7), [
                'scope' => 'global',
                'title' => 'Comunicado',
                'body' => 'Texto atualizado.',
                'starts_at' => '2026-09-25T08:00',
            ])
            ->assertSessionHas('status');

        $this->assertFalse($this->disk->exists('announcements/'.$file->storedName));
    }

    public function test_the_prune_command_only_lists_by_default_and_deletes_old_unlinked_files_with_the_flag(): void
    {
        $linked = $this->attachments->store(UploadedFile::fake()->image('ligado.png', 10, 10));
        $oldOrphan = $this->attachments->store(UploadedFile::fake()->image('orfao.png', 10, 10));
        $recentOrphan = $this->attachments->store(UploadedFile::fake()->image('recente.png', 10, 10));

        $twoHoursAgo = time() - 7200;
        touch($this->disk->path('announcements/'.$linked->storedName), $twoHoursAgo);
        touch($this->disk->path('announcements/'.$oldOrphan->storedName), $twoHoursAgo);
        $this->adminManagementRepository->attachmentNames = [$linked->storedName];

        $this->artisan('monitores:announcements:prune-orphans')
            ->expectsOutputToContain($oldOrphan->storedName)
            ->doesntExpectOutputToContain($recentOrphan->storedName)
            ->assertSuccessful();

        $this->assertTrue($this->disk->exists('announcements/'.$oldOrphan->storedName));

        $this->artisan('monitores:announcements:prune-orphans', ['--delete' => true])->assertSuccessful();

        $this->assertFalse($this->disk->exists('announcements/'.$oldOrphan->storedName));
        $this->assertTrue($this->disk->exists('announcements/'.$linked->storedName));
        $this->assertTrue($this->disk->exists('announcements/'.$recentOrphan->storedName));
    }
}
