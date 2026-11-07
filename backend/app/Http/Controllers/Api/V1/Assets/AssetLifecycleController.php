<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetMaintenance;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Assets\AssetLifecycle;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Repairs and write-offs. */
class AssetLifecycleController extends Controller
{
    public function __construct(private readonly AssetLifecycle $lifecycle) {}

    public function maintenances(Asset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);
        $rows = AssetMaintenance::query()->where('asset_id', $asset->id)->with(['ticket', 'creator'])->orderByDesc('started_at')->get();

        return ApiResponse::data($rows->map(fn ($m) => $this->maintenanceRow($m))->values());
    }

    public function openMaintenance(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('update', $asset);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'vendor' => ['nullable', 'string', 'max:200'],
            'ticket_id' => ['nullable', 'integer'],
        ]);
        $user = $this->user($request);
        if (! empty($data['ticket_id']) && ! Ticket::query()->visibleTo($user)->whereKey((int) $data['ticket_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_id' => __('messages.ticket_link_invalid')]);
        }
        $maintenance = $this->lifecycle->openMaintenance($asset, $data['description'], $data['vendor'] ?? null, $data['ticket_id'] ?? null, $user);

        return ApiResponse::data($this->maintenanceRow($maintenance->load(['ticket', 'creator'])), 201);
    }

    public function closeMaintenance(Request $request, Asset $asset, int $maintenance): JsonResponse
    {
        Gate::authorize('update', $asset);
        $data = $request->validate([
            'result' => ['required', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ]);
        $row = AssetMaintenance::query()->where('asset_id', $asset->id)->find($maintenance) ?? throw new NotFoundHttpException;
        $row = $this->lifecycle->closeMaintenance($asset, $row, $data['result'], isset($data['cost']) ? (string) $data['cost'] : null, $this->user($request));

        return ApiResponse::data($this->maintenanceRow($row->load(['ticket', 'creator'])));
    }

    public function requestDisposal(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('update', $asset);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'act_number' => ['nullable', 'string', 'max:100'],
        ]);
        $disposal = $this->lifecycle->requestDisposal($asset, $data['reason'], $data['act_number'] ?? null, $this->user($request));

        return ApiResponse::data($this->disposalRow($disposal->load(['asset.organization', 'requester', 'decider'])), 201);
    }

    public function disposals(Request $request): JsonResponse
    {
        Gate::authorize('assets.view');
        $query = AssetDisposal::query()->inScope()->with(['asset.organization', 'requester', 'decider'])->latest('requested_at');
        if ($request->filled('filter.status')) {
            $query->whereIn('status', explode(',', (string) $request->input('filter.status')));
        }
        if ($request->filled('filter.asset')) {
            $query->where('asset_id', $request->integer('filter.asset'));
        }
        $page = $query->paginate(Pagination::perPage($request));

        return ApiResponse::data(collect($page->items())->map(fn ($d) => $this->disposalRow($d))->values(), meta: [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
        ]);
    }

    public function approve(Request $request, AssetDisposal $assetDisposal): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->lifecycle->decide($assetDisposal, true, $data['note'] ?? null, $this->user($request));

        return ApiResponse::data($this->disposalRow($assetDisposal->refresh()->load(['asset.organization', 'requester', 'decider'])));
    }

    public function reject(Request $request, AssetDisposal $assetDisposal): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->lifecycle->decide($assetDisposal, false, $data['note'], $this->user($request));

        return ApiResponse::data($this->disposalRow($assetDisposal->refresh()->load(['asset.organization', 'requester', 'decider'])));
    }

    /** @return array<string, mixed> */
    private function maintenanceRow(AssetMaintenance $m): array
    {
        return [
            'id' => $m->id, 'status' => $m->status->value, 'description' => $m->description, 'vendor' => $m->vendor,
            'result' => $m->result, 'cost' => $m->cost,
            'ticket' => $m->ticket ? ['id' => $m->ticket->id, 'number' => $m->ticket->number] : null,
            'started_at' => $m->started_at->toIso8601String(), 'finished_at' => $m->finished_at?->toIso8601String(),
            'created_by' => $m->creator?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function disposalRow(AssetDisposal $d): array
    {
        $user = request()->user();

        return [
            'id' => $d->id, 'status' => $d->status->value, 'reason' => $d->reason, 'act_number' => $d->act_number,
            'asset' => [
                'id' => $d->asset->id, 'inventory_number' => $d->asset->inventory_number, 'name' => $d->asset->name,
                'organization' => $d->asset->organization?->name,
            ],
            'requested_by' => $d->requester?->name, 'requested_at' => $d->requested_at->toIso8601String(),
            'decided_by' => $d->decider?->name, 'decided_at' => $d->decided_at?->toIso8601String(), 'decision_note' => $d->decision_note,
            'can_decide' => $user instanceof User && $d->status->value === 'pending'
                && $user->can('assets.dispose') && $user->canAccessOrganization($d->asset->organization_id),
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
