<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assets\CatalogResource;
use App\Models\Asset;
use App\Models\Location;
use App\Rules\AccessibleOrganization;
use App\Support\ApiResponse;
use App\Support\QueryFilters\OrganizationSubtreeFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Buildings and rooms of the organizations in the user's scope. */
class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('assets.use');
        $query = Location::query()->with(['organization', 'parent'])->orderBy('name');
        if ($request->filled('filter.organization')) {
            (new OrganizationSubtreeFilter)($query, $request->input('filter.organization'), 'organization');
        }

        return ApiResponse::data($query->get()->map(fn ($l) => CatalogResource::location($l))->values());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('assets.update');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            ...$this->rules(),
        ]);
        $this->checkParent($request, (int) $data['organization_id']);

        return ApiResponse::data(CatalogResource::location(Location::create($data)->load(['organization', 'parent'])), 201);
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        Gate::authorize('assets.update');
        $data = $request->validate($this->rules());
        $this->checkParent($request, $location->organization_id, $location->id);
        $location->update($data);

        return ApiResponse::data(CatalogResource::location($location->load(['organization', 'parent'])));
    }

    public function destroy(Location $location): JsonResponse
    {
        Gate::authorize('assets.update');
        if ($location->children()->exists() || Asset::query()->withoutGlobalScopes()->where('location_id', $location->id)->exists()) {
            throw new DomainConflictException(__('messages.catalog_in_use'));
        }
        $location->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name' => [request()->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** The parent must be another location of the same organization. */
    private function checkParent(Request $request, int $organizationId, ?int $selfId = null): void
    {
        if (! $request->filled('parent_id')) {
            return;
        }
        $parent = Location::query()->withoutGlobalScopes()->find($request->integer('parent_id'));
        if ($parent === null || $parent->organization_id !== $organizationId || $parent->id === $selfId) {
            throw ValidationException::withMessages(['parent_id' => __('messages.location_parent_invalid')]);
        }
    }
}
