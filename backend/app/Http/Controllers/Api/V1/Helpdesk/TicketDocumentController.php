<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\Ticket;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Documents linked to a ticket that the user may also see. */
class TicketDocumentController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('documents.view')) {
            return ApiResponse::data([]);
        }

        $documents = Document::query()
            ->whereHas('links', fn ($q) => $q->where('linkable_type', 'ticket')->where('linkable_id', $ticket->id))
            ->with(['organization', 'currentVersion.file', 'currentVersion.uploader'])
            ->orderByDesc('document_date')->get();

        return ApiResponse::data(DocumentResource::collection($documents));
    }
}
