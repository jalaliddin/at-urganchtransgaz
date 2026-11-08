<?php

namespace App\Http\Controllers\Api\V1\Software;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Software\SoftwareImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** CSV import of installed software or antivirus status (synchronous, per-line report). */
class ImportController extends Controller
{
    public function __invoke(Request $request, SoftwareImportService $imports): JsonResponse
    {
        Gate::authorize('software.install');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(SoftwareImportService::COLUMNS))],
            'file' => ['required', 'file', 'extensions:csv,txt', new SafeUpload],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return ApiResponse::data($imports->import($data['type'], (string) $file->getRealPath(), $user));
    }
}
