<?php

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Http\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Services\Organizations\OrganizationService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use App\Support\QueryFilters\OrganizationSubtreeFilter;
use App\Support\Search\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        $base = Organization::query()->with('parent')->withCount('children');
        SearchTerm::whereContains($base, ['name', 'short_name', 'code', 'tin'], (string) $request->query('search', ''));

        $page = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('parent', 'parent_id'),
                AllowedFilter::custom('organization', new OrganizationSubtreeFilter),
            ])
            ->allowedSorts(['name', 'code', 'sort_order', 'created_at'])
            ->defaultSort('sort_order', 'name')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, OrganizationResource::class);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $this->organizations->create($request->validated());

        return ApiResponse::data(new OrganizationResource($organization->load('parent')), 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        Gate::authorize('view', $organization);

        return ApiResponse::data(new OrganizationResource($organization->load('parent')->loadCount('children')));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $organization = $this->organizations->update($organization, $request->validated());

        return ApiResponse::data(new OrganizationResource($organization->load('parent')));
    }

    public function destroy(Organization $organization): JsonResponse
    {
        Gate::authorize('delete', $organization);

        $this->organizations->delete($organization);

        return ApiResponse::message(__('messages.deleted'));
    }
}
