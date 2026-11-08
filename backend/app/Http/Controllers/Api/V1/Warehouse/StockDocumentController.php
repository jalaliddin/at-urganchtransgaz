<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Enums\StockDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StockDocumentRequest;
use App\Http\Resources\Warehouse\StockResource;
use App\Models\StockDocument;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Files\FileStore;
use App\Services\Warehouse\StockService;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Posted stock documents: list, post, detail, scanned paper document. */
class StockDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('warehouse.view');
        $user = $this->user($request);
        $query = StockDocument::query()->visibleTo($user)->with(StockResource::DOCUMENT_RELATIONS)->withCount('lines')->latest('id');

        if ($request->filled('filter.type')) {
            $query->whereIn('type', explode(',', (string) $request->input('filter.type')));
        }
        if ($request->filled('filter.warehouse')) {
            $id = $request->integer('filter.warehouse');
            $query->where(fn ($q) => $q->where('warehouse_id', $id)->orWhere('target_warehouse_id', $id));
        }
        if ($request->filled('filter.material')) {
            $query->whereHas('lines', fn ($q) => $q->where('material_id', $request->integer('filter.material')));
        }
        if ($request->filled('filter.organization')) {
            $ids = OrganizationSubtree::ids($request->integer('filter.organization'));
            $query->where(fn ($q) => $q->whereIn('organization_id', $ids)->orWhereIn('target_organization_id', $ids));
        }
        if ($request->filled('filter.date_from')) {
            $query->whereDate('document_date', '>=', (string) $request->input('filter.date_from'));
        }
        if ($request->filled('filter.date_to')) {
            $query->whereDate('document_date', '<=', (string) $request->input('filter.date_to'));
        }
        $search = trim((string) $request->input('filter.search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('supplier', 'like', "%{$search}%")
                ->orWhere('supplier_document', 'like', "%{$search}%")->orWhere('reason', 'like', "%{$search}%"));
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (StockDocument $d) => [...StockResource::document($d), 'lines_count' => (int) $d->getAttribute('lines_count')]);
    }

    public function store(StockDocumentRequest $request, StockService $stock): JsonResponse
    {
        $type = $request->documentType();
        Gate::authorize($type->permission());
        $user = $this->user($request);
        $document = $stock->post($type, $request->documentHeader(), $request->documentLines(), $user);

        return $this->detail($document, $user, 201);
    }

    public function show(Request $request, StockDocument $stockDocument): JsonResponse
    {
        Gate::authorize('warehouse.view');

        return $this->detail($stockDocument, $this->user($request));
    }

    /** Scanned invoice / waybill / act; replaces the previous file. Only the posting side may attach. */
    public function attachFile(Request $request, StockDocument $stockDocument, FileStore $files): JsonResponse
    {
        Gate::authorize($stockDocument->type->permission());
        $user = $this->user($request);
        if (! $user->canAccessOrganization($stockDocument->organization_id)) {
            throw new AuthorizationException;
        }
        $request->validate(['file' => ['required', new SafeUpload]]);
        $upload = $request->file('file');
        abort_unless($upload instanceof UploadedFile, 422);

        $old = $stockDocument->file;
        $stored = $files->store($upload, $stockDocument->organization_id, $user);
        DB::transaction(fn () => $stockDocument->forceFill(['file_id' => $stored->id])->save());
        if ($old !== null) {
            $files->delete($old);
        }

        return $this->detail($stockDocument->refresh(), $user);
    }

    private function detail(StockDocument $document, User $user, int $status = 200): JsonResponse
    {
        $document->load(StockResource::DOCUMENT_RELATIONS);
        $returns = $document->type === StockDocumentType::Issue
            ? StockDocument::query()->visibleTo($user)->where('type', StockDocumentType::Return)->where('related_document_id', $document->id)
                ->orderBy('id')->get()->map(fn (StockDocument $r) => ['id' => $r->id, 'number' => $r->number, 'document_date' => $r->document_date->toDateString()])->values()
            : [];

        return ApiResponse::data([
            ...StockResource::documentDetail($document),
            'returns' => $returns,
            'can' => ['attach_file' => $user->can($document->type->permission()) && $user->canAccessOrganization($document->organization_id)],
        ], $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
