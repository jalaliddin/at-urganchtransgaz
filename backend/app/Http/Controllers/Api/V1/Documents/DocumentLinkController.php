<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentLinkTargets;
use App\Services\Documents\DocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentLinkController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentLinkTargets $targets,
    ) {}

    public function store(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);
        $data = $request->validate([
            'type' => ['required', Rule::in(DocumentLinkTargets::TYPES)],
            'id' => ['required', 'integer'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $link = $this->documents->link($document, $data['type'], (int) $data['id'], $user);
        $target = $this->targets->find($link->linkable_type, $link->linkable_id, $user);

        return ApiResponse::data([
            'id' => $link->id, 'type' => $link->linkable_type, 'target' => $target ? $this->targets->summary($target) : null,
        ], 201);
    }

    public function destroy(Document $document, int $link): JsonResponse
    {
        Gate::authorize('update', $document);
        $row = $document->links()->find($link) ?? throw new NotFoundHttpException;
        $row->delete();

        return ApiResponse::message(__('messages.deleted'));
    }
}
