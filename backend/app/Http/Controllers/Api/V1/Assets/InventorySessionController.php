<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Assets\InventoryService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventorySessionController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('assets.use');
        $page = InventorySession::query()->with(['organization', 'starter'])->latest('started_at')->paginate(Pagination::perPage($request));

        return ApiResponse::data(collect($page->items())->map(fn ($s) => $this->row($s))->values(), meta: [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('assets.inventory');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'include_descendants' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $session = $this->inventory->start((int) $data['organization_id'], (bool) ($data['include_descendants'] ?? true), $data['name'], $data['note'] ?? null, $this->user($request));

        return ApiResponse::data($this->row($session->load(['organization', 'starter'])), 201);
    }

    public function show(InventorySession $inventorySession): JsonResponse
    {
        Gate::authorize('assets.use');
        $this->inventory->refreshCounts($inventorySession);

        return ApiResponse::data($this->row($inventorySession->load(['organization', 'starter'])));
    }

    public function items(Request $request, InventorySession $inventorySession): JsonResponse
    {
        Gate::authorize('assets.use');
        $query = InventoryItem::query()->where('session_id', $inventorySession->id)->with(['asset.category', 'asset.location.parent', 'scanner']);
        match ($request->input('filter.state')) {
            'found' => $query->where('expected', true)->whereNotNull('found_at'),
            'missing' => $query->where('expected', true)->whereNull('found_at'),
            'unexpected' => $query->where('expected', false),
            default => null,
        };
        $page = $query->orderByDesc('found_at')->orderBy('inventory_number')->paginate(Pagination::perPage($request));

        return ApiResponse::data(collect($page->items())->map(fn ($i) => $this->itemRow($i, $this->user($request)))->values(), meta: [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
        ]);
    }

    public function scan(Request $request, InventorySession $inventorySession): JsonResponse
    {
        Gate::authorize('assets.inventory');
        $data = $request->validate(['code' => ['required', 'string', 'max:500'], 'note' => ['nullable', 'string', 'max:500']]);
        $user = $this->user($request);
        ['result' => $result, 'item' => $item] = $this->inventory->scan($inventorySession, $data['code'], $data['note'] ?? null, $user);
        $this->inventory->refreshCounts($inventorySession);

        return ApiResponse::data([
            'result' => $result,
            'item' => $this->itemRow($item->load(['asset.category', 'asset.location.parent', 'scanner']), $user),
            'session' => $this->row($inventorySession->load(['organization', 'starter'])),
        ]);
    }

    public function close(Request $request, InventorySession $inventorySession): JsonResponse
    {
        Gate::authorize('assets.inventory');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->inventory->close($inventorySession, $data['note'] ?? null, $this->user($request));

        return ApiResponse::data($this->row($inventorySession->load(['organization', 'starter'])));
    }

    /** @return array<string, mixed> */
    private function row(InventorySession $s): array
    {
        return [
            'id' => $s->id, 'name' => $s->name, 'status' => $s->status->value, 'note' => $s->note,
            'organization' => ['id' => $s->organization->id, 'name' => $s->organization->name],
            'include_descendants' => $s->include_descendants,
            'started_by' => $s->starter?->name, 'started_at' => $s->started_at->toIso8601String(),
            'closed_at' => $s->closed_at?->toIso8601String(),
            'expected_count' => $s->expected_count, 'found_count' => $s->found_count,
            'missing_count' => $s->missing_count, 'unexpected_count' => $s->unexpected_count,
        ];
    }

    /** @return array<string, mixed> */
    private function itemRow(InventoryItem $i, User $user): array
    {
        $asset = $i->asset;
        $visible = $asset !== null && $user->canAccessOrganization($asset->organization_id);

        return [
            'id' => $i->id, 'inventory_number' => $i->inventory_number, 'expected' => $i->expected,
            'state' => ! $i->expected ? 'unexpected' : ($i->found_at ? 'found' : 'missing'),
            'found_at' => $i->found_at?->toIso8601String(), 'scanned_by' => $i->scanner?->name, 'note' => $i->note,
            'asset' => $visible ? [
                'id' => $asset->id, 'inventory_number' => $asset->inventory_number, 'name' => $asset->name,
                'category' => $asset->category?->localizedName(), 'status' => $asset->status->value,
                'location' => $asset->location?->fullName(), 'room' => $asset->room,
            ] : null,
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
