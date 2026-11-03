<?php

namespace Tests\Feature\Files;

use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\FileAccess;
use App\Services\Files\FileStore;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class FileDownloadTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedRbac();
        $this->user = $this->makeUser(Roles::SUPER_ADMIN);
    }

    private function storePdf(): StoredFile
    {
        return app(FileStore::class)->store(
            UploadedFile::fake()->createWithContent('Hisobot 1.pdf', SafeUploadTest::pdf()),
            null,
            $this->user,
        );
    }

    public function test_store_keeps_file_private_with_metadata(): void
    {
        $file = $this->storePdf();

        $this->assertSame('local', $file->disk);
        $this->assertMatchesRegularExpression('#^files/\d{4}/\d{2}/[0-9a-f-]{36}$#', $file->path);
        $this->assertSame('Hisobot 1.pdf', $file->original_name);
        $this->assertSame('application/pdf', $file->mime);
        $this->assertSame('pdf', $file->extension);
        $this->assertSame(hash('sha256', SafeUploadTest::pdf()), $file->sha256);
        $this->assertSame($this->user->id, $file->uploaded_by);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_download_without_owner_resolver_is_not_found(): void
    {
        $file = $this->storePdf();

        $this->actingAs($this->user)->get("/api/v1/files/{$file->uuid}")->assertNotFound();
    }

    public function test_download_when_resolver_allows(): void
    {
        $file = $this->storePdf();
        app(FileAccess::class)->register(fn (User $u, StoredFile $f): ?bool => $f->is($file) ? true : null);

        $response = $this->actingAs($this->user)->get("/api/v1/files/{$file->uuid}")->assertOk();

        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame(SafeUploadTest::pdf(), $response->streamedContent());
    }

    public function test_resolver_denial_wins_and_unknown_uuid_is_404(): void
    {
        $file = $this->storePdf();
        app(FileAccess::class)->register(fn (User $u, StoredFile $f): ?bool => false);
        app(FileAccess::class)->register(fn (User $u, StoredFile $f): ?bool => true);

        $this->actingAs($this->user)->get("/api/v1/files/{$file->uuid}")->assertNotFound();
        $this->actingAs($this->user)->get('/api/v1/files/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_guest_gets_401(): void
    {
        $file = $this->storePdf();

        $this->getJson("/api/v1/files/{$file->uuid}")->assertUnauthorized();
    }
}
