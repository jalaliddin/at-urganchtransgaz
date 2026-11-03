<?php

namespace App\Services\Files;

use App\Models\StoredFile;
use App\Models\User;
use App\Rules\SafeUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes files to the private disk under an opaque name (no extension, so the
 * web server can never execute or serve them directly) and records metadata.
 */
class FileStore
{
    public const DISK = 'local';

    public function store(UploadedFile $file, ?int $organizationId, ?User $by): StoredFile
    {
        $uuid = (string) Str::uuid();
        $directory = 'files/'.now()->format('Y/m');
        $realPath = (string) $file->getRealPath();

        if (Storage::disk(self::DISK)->putFileAs($directory, $file, $uuid) === false) {
            throw new RuntimeException('Could not store the uploaded file.');
        }

        return StoredFile::create([
            'uuid' => $uuid,
            'disk' => self::DISK,
            'path' => "{$directory}/{$uuid}",
            'original_name' => self::cleanName($file->getClientOriginalName()),
            'mime' => SafeUpload::sniff($file),
            'extension' => strtolower($file->getClientOriginalExtension()),
            'size' => (int) $file->getSize(),
            'sha256' => (string) hash_file('sha256', $realPath),
            'organization_id' => $organizationId,
            'uploaded_by' => $by?->id,
        ]);
    }

    public function storeContents(string $contents, string $name, string $mime, ?int $organizationId, ?User $by): StoredFile
    {
        $uuid = (string) Str::uuid();
        $path = 'files/'.now()->format('Y/m')."/{$uuid}";

        if (! Storage::disk(self::DISK)->put($path, $contents)) {
            throw new RuntimeException('Could not store the generated file.');
        }

        return StoredFile::create([
            'uuid' => $uuid,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => self::cleanName($name),
            'mime' => $mime,
            'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'organization_id' => $organizationId,
            'uploaded_by' => $by?->id,
        ]);
    }

    public function delete(StoredFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }

    /** Basename without control/path characters, at most 255 bytes. */
    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"]/u', '', $name);
        $name = trim($name) === '' ? 'file' : trim($name);

        return mb_strcut($name, 0, 255);
    }
}
