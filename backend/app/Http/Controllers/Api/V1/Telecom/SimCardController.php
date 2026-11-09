<?php

namespace App\Http\Controllers\Api\V1\Telecom;

use App\Enums\AuditEvent;
use App\Enums\SimStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Telecom\SimResource;
use App\Models\ServiceProvider;
use App\Models\SimCard;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentLinkTargets;
use App\Services\Sim\SimCards;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** SIM cards of the user's organizations (global scope). ICCID masked; reveal audited. */
class SimCardController extends Controller
{
    private const RELATIONS = ['provider', 'organization', 'department', 'employee'];

    public function __construct(private readonly SimCards $cards) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('sim.use');
        $query = SimCard::query()->with(self::RELATIONS)->orderBy('phone_number');
        $this->filter($query, $request);
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (SimCard $c) => SimResource::card($c));
    }

    public function show(SimCard $simCard): JsonResponse
    {
        Gate::authorize('sim.use');

        return $this->detail($simCard);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('sim.manage');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'phone_number' => ['required', 'string', 'max:30'],
            'iccid' => ['nullable', 'string', 'max:40'],
            ...$this->rules(true),
        ]);
        $card = $this->cards->register($data, $this->user($request));

        return $this->detail($card, 201);
    }

    public function update(Request $request, SimCard $simCard): JsonResponse
    {
        Gate::authorize('sim.manage');
        $data = $request->validate(['phone_number' => ['sometimes', 'string', 'max:30'], ...$this->rules(false)]);

        return $this->detail($this->cards->update($simCard, $data, $this->user($request)));
    }

    public function destroy(SimCard $simCard): JsonResponse
    {
        Gate::authorize('sim.manage');
        $this->cards->delete($simCard);

        return ApiResponse::message(__('messages.deleted'));
    }

    public function reveal(SimCard $simCard, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('sim.manage');
        $audit->record(AuditEvent::SecretRevealed, $simCard, [], ['field' => 'iccid']);

        return ApiResponse::data(['iccid' => $simCard->iccid]);
    }

    public function documents(Request $request, SimCard $simCard, DocumentLinkTargets $targets): JsonResponse
    {
        Gate::authorize('sim.use');

        return ApiResponse::data($targets->linkedDocuments('sim_card', $simCard->id, $this->user($request)));
    }

    /** @param  Builder<SimCard>  $query */
    private function filter(Builder $query, Request $request): void
    {
        if ($request->filled('filter.provider')) {
            $query->where('provider_id', $request->integer('filter.provider'));
        }
        // One status or several, comma separated (e.g. "in_stock,returned" for free cards).
        $statuses = array_values(array_filter(array_map(
            fn (string $v) => SimStatus::tryFrom(trim($v))?->value, explode(',', (string) $request->input('filter.status')))));
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]);
        }
        if ($request->filled('filter.employee')) {
            $query->where('employee_id', $request->integer('filter.employee'));
        }
        $search = trim((string) $request->input('filter.search'));
        if ($search !== '') {
            $iccid = SimCard::normalizeIccid($search);
            $digits = preg_replace('/\D+/', '', $search) ?? '';
            $query->where(function (Builder $w) use ($search, $iccid, $digits): void {
                $w->where('account_number', 'like', '%'.$search.'%')->orWhere('tariff', 'like', '%'.$search.'%');
                if ($digits !== '') {
                    $w->orWhere('phone_number', 'like', '%'.$digits.'%');
                }
                if ($iccid !== null) {
                    $w->orWhere('iccid_hash', SimCard::iccidHash($iccid));
                }
            });
        }
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating): array
    {
        return [
            'provider_id' => [$creating ? 'required' : 'sometimes', 'integer',
                Rule::exists(ServiceProvider::class, 'id')->where('is_mobile_operator', true)],
            'tariff' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function detail(SimCard $card, int $status = 200): JsonResponse
    {
        $card->load([...self::RELATIONS, 'assignments' => fn ($q) => $q->with(['employee', 'department'])->latest('id'),
            'histories' => fn ($q) => $q->with('user')->latest('id')->limit(100)]);

        return ApiResponse::data([
            ...SimResource::card($card),
            'assignments' => $card->assignments->map(fn ($a) => SimResource::assignment($a))->values(),
            'history' => $card->histories->map(fn ($h) => SimResource::history($h))->values(),
        ], $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
