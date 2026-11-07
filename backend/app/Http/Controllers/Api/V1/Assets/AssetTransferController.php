<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Models\AssetTransfer;
use App\Models\AssetTransferItem;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Assets\AssetTransferService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssetTransferController extends Controller
{
    private const RELATIONS = ['fromOrganization', 'toOrganization', 'act', 'creator'];

    public function __construct(private readonly AssetTransferService $transfers) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('assets.view');
        $user = $this->user($request);
        $scope = $user->accessScope();
        $direction = $request->input('filter.direction');

        $query = AssetTransfer::query()->visibleTo($user)->with(self::RELATIONS)->withCount('items')->latest('id');
        if (in_array($direction, ['incoming', 'outgoing'], true) && ! $scope->isUnrestricted()) {
            $query->whereIn($direction === 'incoming' ? 'to_organization_id' : 'from_organization_id', $scope->organizationIds());
        }
        if ($request->filled('filter.status')) {
            $query->whereIn('status', explode(',', (string) $request->input('filter.status')));
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::data(
            collect($page->items())->map(fn (AssetTransfer $t) => $this->row($t, $user))->values(),
            meta: ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
                'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem()],
        );
    }

    /** Receiving organizations may be outside the sender's scope: all active ones (id + name only). */
    public function destinations(): JsonResponse
    {
        Gate::authorize('assets.transfer');
        $rows = DB::table('organizations')->whereNull('deleted_at')->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($o) => ['id' => (int) $o->id, 'name' => (string) $o->name]);

        return ApiResponse::data($rows->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_organization_id' => ['required', 'integer', new AccessibleOrganization],
            'to_organization_id' => ['required', 'integer', 'different:from_organization_id',
                Rule::exists('organizations', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:1000'],
            'asset_ids' => ['sometimes', 'array', 'max:500'],
            'asset_ids.*' => ['integer'],
        ]);
        $user = $this->user($request);
        $transfer = DB::transaction(function () use ($data, $user): AssetTransfer {
            $transfer = $this->transfers->create((int) $data['from_organization_id'], (int) $data['to_organization_id'], $data['note'] ?? null, $user);
            if (! empty($data['asset_ids'])) {
                $this->transfers->addItems($transfer, array_map('intval', $data['asset_ids']), null, $user);
            }

            return $transfer;
        });

        return $this->detail($transfer, $user, 201);
    }

    public function show(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        Gate::authorize('assets.view');

        return $this->detail($assetTransfer, $this->user($request));
    }

    public function addItems(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $data = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1', 'max:500'], 'asset_ids.*' => ['integer'],
            'condition_note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->transfers->addItems($assetTransfer, array_map('intval', $data['asset_ids']), $data['condition_note'] ?? null, $this->user($request));

        return $this->detail($assetTransfer, $this->user($request));
    }

    public function removeItem(Request $request, AssetTransfer $assetTransfer, int $asset): JsonResponse
    {
        $this->transfers->removeItem($assetTransfer, $asset, $this->user($request));

        return $this->detail($assetTransfer, $this->user($request));
    }

    public function send(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $this->transfers->send($assetTransfer, $this->user($request));

        return $this->detail($assetTransfer, $this->user($request));
    }

    public function receive(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->transfers->receive($assetTransfer, $this->user($request), $data['note'] ?? null);

        return $this->detail($assetTransfer, $this->user($request));
    }

    public function cancel(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $this->transfers->cancel($assetTransfer, $this->user($request));

        return $this->detail($assetTransfer, $this->user($request));
    }

    private function detail(AssetTransfer $transfer, User $user, int $status = 200): JsonResponse
    {
        $transfer->refresh()->load([...self::RELATIONS, 'items.asset.category'])->loadCount('items');
        $row = $this->row($transfer, $user);
        $row['items'] = $transfer->items->map(fn (AssetTransferItem $i) => [
            'asset' => [
                'id' => $i->asset->id, 'inventory_number' => $i->asset->inventory_number, 'name' => $i->asset->name,
                'category' => $i->asset->category?->localizedName(), 'serial_number' => $i->asset->serial_number,
            ],
            'condition_note' => $i->condition_note,
        ])->values();

        return ApiResponse::data($row, $status);
    }

    /** @return array<string, mixed> */
    private function row(AssetTransfer $t, User $user): array
    {
        $sender = $user->can('assets.transfer') && $user->canAccessOrganization($t->from_organization_id);
        $receiver = $user->can('assets.transfer') && $user->canAccessOrganization($t->to_organization_id);

        return [
            'id' => $t->id,
            'number' => $t->number,
            'status' => $t->status->value,
            'direction' => $user->canAccessOrganization($t->from_organization_id) ? 'outgoing' : 'incoming',
            'from' => ['id' => $t->fromOrganization->id, 'name' => $t->fromOrganization->name],
            'to' => ['id' => $t->toOrganization->id, 'name' => $t->toOrganization->name],
            'note' => $t->note,
            'items_count' => (int) ($t->items_count ?? 0),
            'act' => $t->act ? ['uuid' => $t->act->uuid, 'name' => $t->act->original_name] : null,
            'created_by' => $t->creator?->name,
            'created_at' => $t->created_at->toIso8601String(),
            'sent_at' => $t->sent_at?->toIso8601String(),
            'received_at' => $t->received_at?->toIso8601String(),
            'cancelled_at' => $t->cancelled_at?->toIso8601String(),
            'can' => [
                'edit' => $sender && $t->status === TransferStatus::Draft,
                'send' => $sender && $t->status === TransferStatus::Draft,
                'cancel' => $sender && $t->status->isOpen(),
                'receive' => $receiver && $t->status === TransferStatus::Sent,
            ],
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
