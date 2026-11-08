<?php

namespace App\Http\Controllers\Api\V1\Telecom;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Telecom\ServiceResource;
use App\Models\ItService;
use App\Models\ServiceContract;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Documents\DocumentLinkTargets;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Provider contracts of the user's organizations (global scope). */
class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('services.use');
        $today = CarbonImmutable::today((string) config('app.display_timezone'))->toDateString();
        $query = ServiceContract::query()->with(['provider', 'organization'])->withCount('services')->orderByDesc('valid_to')->orderBy('id');
        if ($request->filled('filter.provider')) {
            $query->where('provider_id', $request->integer('filter.provider'));
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]);
        }
        $notTerminated = fn ($q) => $q->where(fn ($w) => $w->whereNull('terminated_at')->orWhere('terminated_at', '>', $today));
        match ((string) $request->input('filter.status')) {
            'active' => $query->where($notTerminated)->where(fn ($w) => $w->whereNull('valid_to')->orWhere('valid_to', '>=', $today)),
            'expired' => $query->where($notTerminated)->where('valid_to', '<', $today),
            'terminated' => $query->where('terminated_at', '<=', $today),
            'expiring' => $query->where($notTerminated)->whereBetween('valid_to', [$today,
                CarbonImmutable::parse($today)->addDays((int) config('telecom.contract_notice_days'))->toDateString()]),
            default => null,
        };
        $search = trim((string) $request->input('filter.search'));
        if ($search !== '') {
            $query->where(fn ($w) => $w->where('number', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%"));
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (ServiceContract $c) => ServiceResource::contract($c));
    }

    public function show(ServiceContract $serviceContract): JsonResponse
    {
        Gate::authorize('services.use');

        return $this->card($serviceContract);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('services.manage');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'provider_id' => ['required', 'integer', Rule::exists('service_providers', 'id')],
            ...$this->rules($request, null),
        ]);

        return $this->card(ServiceContract::query()->create($data), 201);
    }

    public function update(Request $request, ServiceContract $serviceContract): JsonResponse
    {
        Gate::authorize('services.manage');
        $serviceContract->update($request->validate($this->rules($request, $serviceContract)));

        return $this->card($serviceContract->refresh());
    }

    public function destroy(ServiceContract $serviceContract): JsonResponse
    {
        Gate::authorize('services.manage');
        if (ItService::query()->withoutGlobalScopes()->where('contract_id', $serviceContract->id)->exists()) {
            throw new DomainConflictException(__('messages.contract_in_use'));
        }
        $serviceContract->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    public function documents(Request $request, ServiceContract $serviceContract, DocumentLinkTargets $targets): JsonResponse
    {
        Gate::authorize('services.use');
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::data($targets->linkedDocuments('service_contract', $serviceContract->id, $user));
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?ServiceContract $contract): array
    {
        $providerId = $contract->provider_id ?? $request->integer('provider_id');

        return [
            'number' => [$contract ? 'sometimes' : 'required', 'string', 'max:100',
                Rule::unique('service_contracts', 'number')->where('provider_id', $providerId)->ignore($contract?->id)],
            'subject' => ['nullable', 'string', 'max:255'],
            'signed_at' => ['nullable', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', function (string $attribute, mixed $value, \Closure $fail) use ($request, $contract): void {
                $from = $request->input('valid_from', $contract?->valid_from?->toDateString());
                if (is_string($from) && $from !== '' && is_string($value) && strtotime($value) < strtotime($from)) {
                    $fail(__('validation.after_or_equal', ['attribute' => $attribute, 'date' => $from]));
                }
            }],
            'terminated_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function card(ServiceContract $contract, int $status = 200): JsonResponse
    {
        $contract->load(['provider', 'organization'])->loadCount('services');

        return ApiResponse::data(ServiceResource::contract($contract), $status);
    }
}
