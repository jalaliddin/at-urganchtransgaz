<?php

namespace App\Http\Controllers\Api\V1\Files;

use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use App\Services\Files\FileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Streams a private file after its owner module approved the download. */
class FileDownloadController extends Controller
{
    public function __invoke(Request $request, string $uuid, FileAccess $access): StreamedResponse
    {
        $file = StoredFile::query()->where('uuid', $uuid)->first();
        $user = $request->user();

        if ($file === null || $user === null || ! $access->canDownload($user, $file)
            || ! Storage::disk($file->disk)->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        $inline = $request->boolean('inline') && $file->isImage();
        $headers = [
            'Content-Type' => $inline ? $file->mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return $inline
            ? Storage::disk($file->disk)->response($file->path, $file->original_name, $headers, 'inline')
            : Storage::disk($file->disk)->download($file->path, $file->original_name, $headers);
    }
}
