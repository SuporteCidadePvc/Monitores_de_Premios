<?php

namespace Tests\Unit;

use App\Domain\Announcements\Exceptions\AnnouncementAttachmentRejected;
use App\Infrastructure\Announcements\AnnouncementAttachmentStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AnnouncementAttachmentStoreTest extends TestCase
{
    private Filesystem $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('local');
    }

    public function test_a_valid_png_is_stored_under_a_random_name_with_its_metadata(): void
    {
        $upload = UploadedFile::fake()->image('Cartaz Setembro.png', 1920, 1080);

        $file = $this->store()->store($upload);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $file->storedName);
        $this->assertSame('Cartaz Setembro.png', $file->originalName);
        $this->assertSame('image/png', $file->mimeType);
        $this->assertSame(1920, $file->width);
        $this->assertSame(1080, $file->height);
        $this->assertSame($upload->getSize(), $file->sizeBytes);
        $this->assertSame(hash_file('sha256', $upload->getRealPath()), $file->sha256);
        $this->assertTrue($this->disk->exists('announcements/'.$file->storedName));
    }

    public function test_the_extension_comes_from_the_content_and_not_from_the_client_name(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jpg');
        $image = imagecreatetruecolor(40, 30);
        imagejpeg($image, $path);

        $file = $this->store()->store(new UploadedFile($path, 'foto.png', null, null, true));

        $this->assertSame('image/jpeg', $file->mimeType);
        $this->assertStringEndsWith('.jpg', $file->storedName);
        $this->assertSame(40, $file->width);
    }

    public function test_svg_is_rejected_because_it_can_carry_scripts(): void
    {
        $upload = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->assertRejected(AnnouncementAttachmentRejected::UNSUPPORTED_TYPE, $upload);
    }

    public function test_a_text_file_disguised_as_png_is_rejected_by_its_real_content(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, 'isto não é uma imagem');

        $this->assertRejected(
            AnnouncementAttachmentRejected::UNSUPPORTED_TYPE,
            new UploadedFile($path, 'cartaz.png', 'image/png', null, true),
        );
    }

    public function test_content_that_claims_an_image_type_but_is_not_readable_is_rejected(): void
    {
        // Laravel's fake file reports the type from its extension, exercising the getimagesize() guard.
        $upload = UploadedFile::fake()->createWithContent('cartaz.png', 'isto não é uma imagem');

        $this->assertRejected(AnnouncementAttachmentRejected::NOT_AN_IMAGE, $upload);
    }

    public function test_gif_is_not_accepted_in_phase_one(): void
    {
        $this->assertRejected(AnnouncementAttachmentRejected::UNSUPPORTED_TYPE, UploadedFile::fake()->image('anim.gif', 10, 10));
    }

    public function test_files_above_the_limit_are_rejected_before_being_written(): void
    {
        $upload = UploadedFile::fake()->image('grande.png', 400, 400);

        try {
            $this->store(maxBytes: 100)->store($upload);
            $this->fail('The oversized upload should have been rejected.');
        } catch (AnnouncementAttachmentRejected $exception) {
            $this->assertSame(AnnouncementAttachmentRejected::TOO_LARGE, $exception->reason);
        }

        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_delete_removes_the_file_and_ignores_unsafe_names(): void
    {
        $file = $this->store()->store(UploadedFile::fake()->image('a.png', 10, 10));
        $this->disk->put('outside.png', 'keep');

        $this->store()->delete('../outside.png');
        $this->store()->delete($file->storedName);

        $this->assertFalse($this->disk->exists('announcements/'.$file->storedName));
        $this->assertTrue($this->disk->exists('outside.png'));
    }

    public function test_files_lists_only_names_created_by_the_store(): void
    {
        $file = $this->store()->store(UploadedFile::fake()->image('a.png', 10, 10));
        $this->disk->put('announcements/manual-note.txt', 'x');

        $names = array_column($this->store()->files(), 'name');

        $this->assertSame([$file->storedName], $names);
    }

    private function store(int $maxBytes = 10 * 1024 * 1024): AnnouncementAttachmentStore
    {
        return new AnnouncementAttachmentStore($this->disk, 'announcements', $maxBytes);
    }

    private function assertRejected(string $reason, UploadedFile $upload): void
    {
        try {
            $this->store()->store($upload);
            $this->fail('The upload should have been rejected.');
        } catch (AnnouncementAttachmentRejected $exception) {
            $this->assertSame($reason, $exception->reason);
        }

        $this->assertSame([], $this->disk->allFiles());
    }
}
