<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\AssetRequest;
use App\Http\Resources\Assets\AssetResource;
use App\Http\Resources\Documents\DocumentResource;
use App\Models\Asset;
use App\Models\Document;
use App\Models\User;
use App\Services\Assets\AssetFilters;
use App\Services\Assets\AssetService;
use App\Services\Assets\AssetStats;
use App\Support\ApiResponse;
use App\Support\Pagination;
use App\Support\Search\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetController extends Controller
{
    public const SEARCH_COLUMNS = ['inventory_number', 'serial_number', 'hostname', 'ip_address', 'mac_address', 'name'];

    public function __construct(private readonly AssetService $assets) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Asset::class);
        /** @var User $user */
        $user = $request->user();

        $base = Asset::query()->with(AssetResource::RELATIONS);
        SearchTerm::whereContains($base, self::SEARCH_COLUMNS, SearchTerm::fromRequest($request));

        $page = QueryBuilder::for($base)
            ->allowedFilters(AssetFilters::allowed($user))
            ->allowedSorts(['inventory_number', 'name', 'status', 'warranty_until', 'purchase_date', 'created_at', 'cost'])
            ->defaultSort('inventory_number')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, AssetResource::class);
    }

    public function stats(Request $request, AssetStats $stats): JsonResponse
    {
        Gate::authorize('viewAny', Asset::class);
        $request->validate(['filter' => ['sometimes', 'array:'.implode(',', AssetFilters::NAMES)]]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::data($stats->summary($user, (array) $request->input('filter', [])));
    }

    /** Documents linked to the asset that the user may also see. */
    public function documents(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('documents.view')) {
            return ApiResponse::data([]);
        }
        $documents = Document::query()
            ->whereHas('links', fn ($q) => $q->where('linkable_type', 'asset')->where('linkable_id', $asset->id))
            ->with(['organization', 'currentVersion.file', 'currentVersion.uploader'])->orderByDesc('document_date')->get();

        return ApiResponse::data(DocumentResource::collection($documents));
    }

    public function store(AssetRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $asset = $this->assets->create($request->validated(), $user);

        return ApiResponse::data(new AssetResource($asset->load(AssetResource::RELATIONS)), 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);

        return ApiResponse::data(new AssetResource($asset->load(AssetResource::RELATIONS)));
    }

    /** QR target: find by inventory number within the user's scope. */
    public function lookup(string $inventory): JsonResponse
    {
        Gate::authorize('viewAny', Asset::class);
        $asset = Asset::query()->where('inventory_number', $inventory)->first() ?? throw new NotFoundHttpException;

        return $this->show($asset);
    }

    public function update(AssetRequest $request, Asset $asset): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $asset = $this->assets->update($asset, $request->validated(), $user);

        return ApiResponse::data(new AssetResource($asset->load(AssetResource::RELATIONS)));
    }

    public function status(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('update', $asset);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (AssetStatus $s) => $s->value, AssetStatus::manual()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $this->assets->setStatus($asset, AssetStatus::from($data['status']), $user, $data['note'] ?? null);

        return ApiResponse::data(new AssetResource($asset->refresh()->load(AssetResource::RELATIONS)));
    }

    public function destroy(Asset $asset): JsonResponse
    {
        Gate::authorize('delete', $asset);
        $this->assets->delete($asset);

        return ApiResponse::message(__('messages.deleted'));
    }
}
