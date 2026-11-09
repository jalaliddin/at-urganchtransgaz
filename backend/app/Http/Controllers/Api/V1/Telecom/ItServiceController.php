<?php

namespace App\Http\Controllers\Api\V1\Telecom;

use App\Enums\BillingCycle;
use App\Enums\ServiceType;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Telecom\ServiceResource;
use App\Models\ItService;
use App\Models\ServiceContract;
use App\Models\ServiceInvoice;
use App\Rules\AccessibleOrganization;
use App\Services\Telecom\BillingService;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Internet / communication / IT services of the user's organizations (global scope). */
class ItServiceController extends Controller
{
    private const RELATIONS = ['provider', 'organization', 'contract'];

    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('services.use');
        $query = ItService::query()->with(self::RELATIONS)->orderBy('name')->orderBy('id');
        foreach (['provider' => 'provider_id', 'contract' => 'contract_id'] as $filter => $column) {
            if ($request->filled("filter.{$filter}")) {
                $query->where($column, $request->integer("filter.{$filter}"));
            }
        }
        $type = ServiceType::tryFrom((string) $request->input('filter.type'));
        if ($type !== null) {
            $query->where('type', $type->value);
        }
        if ($request->filled('filter.active')) {
            $query->where('is_active', $request->boolean('filter.active'));
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]);
        }
        $search = trim((string) $request->input('search', $request->input('filter.search')));
        if ($search !== '') {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('identifier', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (ItService $s) => ServiceResource::service($s));
    }

    public function show(ItService $itService): JsonResponse
    {
        Gate::authorize('services.use');

        return $this->card($itService);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('services.manage');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'provider_id' => ['required', 'integer', Rule::exists('service_providers', 'id')],
            'type' => ['required', Rule::enum(ServiceType::class)],
            'name' => ['required', 'string', 'max:200'],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            ...$this->rules(),
        ]);
        $this->checkContract($data['contract_id'] ?? null, (int) $data['organization_id'], (int) $data['provider_id']);

        $service = ItService::query()->create($data);
        $this->billing->generateFor($service->refresh(), BillingService::today());

        return $this->card($service, 201);
    }

    public function update(Request $request, ItService $itService): JsonResponse
    {
        Gate::authorize('services.manage');
        $data = $request->validate([
            'provider_id' => ['sometimes', 'integer', Rule::exists('service_providers', 'id')],
            'type' => ['sometimes', Rule::enum(ServiceType::class)],
            'name' => ['sometimes', 'string', 'max:200'],
            'billing_cycle' => ['sometimes', Rule::enum(BillingCycle::class)],
            ...$this->rules(),
        ]);
        $this->checkContract(array_key_exists('contract_id', $data) ? $data['contract_id'] : $itService->contract_id,
            $itService->organization_id, (int) ($data['provider_id'] ?? $itService->provider_id));
        $itService->update($data);
        $this->billing->generateFor($itService->refresh(), BillingService::today());

        return $this->card($itService);
    }

    /** Only while nothing was invoiced or paid; generated empty periods go with it. Otherwise deactivate it. */
    public function destroy(ItService $itService): JsonResponse
    {
        Gate::authorize('services.manage');
        DB::transaction(function () use ($itService): void {
            $billed = ServiceInvoice::query()->withoutGlobalScopes()->where('service_id', $itService->id)->lockForUpdate()
                ->where(fn ($q) => $q->whereNotNull('amount')->orWhere('paid_amount', '>', 0))->exists();
            if ($billed) {
                throw new DomainConflictException(__('messages.service_billed'));
            }
            ServiceInvoice::query()->withoutGlobalScopes()->where('service_id', $itService->id)->delete();
            $itService->delete();
        });

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'contract_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:100'],
            'tariff' => ['nullable', 'string', 'max:150'],
            'fee' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999'],
            'due_day' => ['sometimes', 'integer', 'between:1,28'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** The contract must be the same organization's contract with the same provider. */
    private function checkContract(mixed $contractId, int $organizationId, int $providerId): void
    {
        if ($contractId === null) {
            return;
        }
        $ok = ServiceContract::query()->withoutGlobalScopes()->whereKey((int) $contractId)
            ->where('organization_id', $organizationId)->where('provider_id', $providerId)->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['contract_id' => __('messages.service_contract_mismatch')]);
        }
    }

    private function card(ItService $service, int $status = 200): JsonResponse
    {
        return ApiResponse::data(ServiceResource::service($service->load(self::RELATIONS)), $status);
    }
}
