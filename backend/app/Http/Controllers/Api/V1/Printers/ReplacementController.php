<?php

namespace App\Http\Controllers\Api\V1\Printers;

use App\Enums\ReplacementSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Printers\PrinterResource;
use App\Models\CartridgeReplacement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Printers\CartridgeReplacements;
use App\Services\Printers\PrinterRegistry;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Cartridge replacements: per printer, journal, record and cancel (design P4–P6, P10). */
class ReplacementController extends Controller
{
    private const RELATIONS = ['organization', 'asset', 'cartridge', 'warehouse', 'stockDocument', 'returnDocument', 'performer', 'canceller', 'counter'];

    public function __construct(private readonly PrinterRegistry $registry, private readonly CartridgeReplacements $replacements) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('printers.view');
        $user = $this->user($request);
        $query = $this->replacements->journal($user, [...(array) $request->input('filter', []), 'search' => $request->input('search')]);

        return $this->page($query, $request, $user);
    }

    public function history(Request $request, int $printer): JsonResponse
    {
        Gate::authorize('printers.view');
        $user = $this->user($request);
        $asset = $this->registry->find($user, $printer);

        return $this->page(CartridgeReplacement::query()->withoutGlobalScopes()->where('asset_id', $asset->id), $request, $user);
    }

    public function store(Request $request, int $printer): JsonResponse
    {
        Gate::authorize('printers.service');
        $user = $this->user($request);
        $asset = $this->registry->find($user, $printer);
        if ($request->input('source') === ReplacementSource::Warehouse->value) {
            Gate::authorize('warehouse.issue');
        }
        $data = $request->validate([
            'cartridge_model_id' => ['required', 'integer', Rule::exists('cartridge_models', 'id')->where('is_active', true)],
            'replaced_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::today((string) config('app.display_timezone'))->toDateString()],
            'source' => ['required', Rule::enum(ReplacementSource::class)],
            'warehouse_id' => [Rule::requiredIf($request->input('source') === ReplacementSource::Warehouse->value), 'nullable', 'integer', $this->visibleWarehouse()],
            'note' => ['nullable', 'string', 'max:1000'],
            'counter' => ['nullable', 'array'],
            'counter.total' => ['required_with:counter', 'integer', 'min:0', 'max:99999999999'],
            'counter.is_reset' => ['sometimes', 'boolean'],
        ]);
        if ($data['source'] !== ReplacementSource::Warehouse->value) {
            unset($data['warehouse_id']);
        }
        $replacement = $this->replacements->replace($asset, $data, $user);

        return ApiResponse::data($this->row($replacement, $user), 201);
    }

    public function cancel(Request $request, int $cartridgeReplacement): JsonResponse
    {
        Gate::authorize('printers.service');
        $user = $this->user($request);
        $replacement = CartridgeReplacement::query()->visibleTo($user)->findOrFail($cartridgeReplacement);
        if ($replacement->warehouse_id !== null) {
            Gate::authorize('warehouse.issue');
            $warehouseOrg = Warehouse::query()->withoutGlobalScopes()->whereKey($replacement->warehouse_id)->value('organization_id');
            if (! $user->canAccessOrganization((int) $warehouseOrg)) {
                throw new AuthorizationException;
            }
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return ApiResponse::data($this->row($this->replacements->cancel($replacement, (string) $data['reason'], $user), $user));
    }

    /** @param  Builder<CartridgeReplacement>  $query */
    private function page(Builder $query, Request $request, User $user): JsonResponse
    {
        /** @var LengthAwarePaginator<int, CartridgeReplacement> $page */
        $page = $query->with(self::RELATIONS)->orderByDesc('replaced_at')->orderByDesc('id')
            ->paginate(Pagination::perPage($request))->withQueryString();
        $pages = $this->replacements->pagesSincePrevious($page->getCollection());

        return ApiResponse::page($page, fn (CartridgeReplacement $r) => PrinterResource::replacement($r, $pages[$r->id] ?? null, $this->canCancel($r, $user)));
    }

    /** @return array<string, mixed> */
    private function row(CartridgeReplacement $replacement, User $user): array
    {
        $replacement->load(self::RELATIONS);

        return PrinterResource::replacement($replacement, $this->replacements->pagesSincePrevious(collect([$replacement]))[$replacement->id] ?? null,
            $this->canCancel($replacement, $user));
    }

    private function canCancel(CartridgeReplacement $r, User $user): bool
    {
        if ($r->cancelled_at !== null || ! $user->can('printers.service') || ! $user->canAccessOrganization($r->organization_id)) {
            return false;
        }

        return $r->warehouse === null || ($user->can('warehouse.issue') && $user->canAccessOrganization($r->warehouse->organization_id));
    }

    /** The warehouse must be one of the user's (global scope applies in requests). */
    private function visibleWarehouse(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! Warehouse::query()->whereKey((int) $value)->exists()) {
                $fail(__('messages.warehouse_not_accessible'));
            }
        };
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
