<?php

namespace App\Http\Controllers\Api\V1\Telecom;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Telecom\SimResource;
use App\Models\ItService;
use App\Models\ServiceContract;
use App\Models\ServiceProvider;
use App\Models\SimCard;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Operators and providers: global reference data. */
class ProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('telecom.use');
        $rows = ServiceProvider::query()->orderBy('name')
            ->when($request->boolean('filter.mobile'), fn ($q) => $q->where('is_mobile_operator', true))
            ->when($request->boolean('filter.active'), fn ($q) => $q->where('is_active', true))
            ->get();

        return ApiResponse::data($rows->map(fn (ServiceProvider $p) => SimResource::provider($p))->values());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('telecom.providers');
        $provider = ServiceProvider::query()->create($request->validate($this->rules(null)));

        return ApiResponse::data(SimResource::provider($provider), 201);
    }

    public function update(Request $request, ServiceProvider $serviceProvider): JsonResponse
    {
        Gate::authorize('telecom.providers');
        $serviceProvider->update($request->validate($this->rules($serviceProvider)));

        return ApiResponse::data(SimResource::provider($serviceProvider));
    }

    public function destroy(ServiceProvider $serviceProvider): JsonResponse
    {
        Gate::authorize('telecom.providers');
        $used = SimCard::query()->withoutGlobalScopes()->where('provider_id', $serviceProvider->id)->exists()
            || ServiceContract::query()->withoutGlobalScopes()->where('provider_id', $serviceProvider->id)->exists()
            || ItService::query()->withoutGlobalScopes()->where('provider_id', $serviceProvider->id)->exists();
        if ($used) {
            throw new DomainConflictException(__('messages.provider_in_use'));
        }
        $serviceProvider->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(?ServiceProvider $provider): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('service_providers', 'name')->ignore($provider?->id)],
            'tin' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_mobile_operator' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
