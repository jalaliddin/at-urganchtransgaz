<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Documents\DocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentVersionController extends Controller
{
    public function store(Request $request, Document $document, DocumentService $documents): JsonResponse
    {
        Gate::authorize('update', $document);
        $data = $request->validate([
            'file' => ['required', new SafeUpload],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $version = $documents->addVersion($document, $request->file('file'), $data['note'] ?? null, $user);

        return ApiResponse::data(DocumentResource::version($version->load(['file', 'uploader'])), 201);
    }
}
