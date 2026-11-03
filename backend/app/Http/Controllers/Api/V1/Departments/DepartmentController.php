<?php

namespace App\Http\Controllers\Api\V1\Departments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Http\Resources\Departments\DepartmentResource;
use App\Models\Department;
use App\Services\Departments\DepartmentService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use App\Support\QueryFilters\OrganizationSubtreeFilter;
use App\Support\Search\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DepartmentController extends Controller
{
    private const RELATIONS = ['organization', 'parent'];

    public function __construct(private readonly DepartmentService $departments) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $base = Department::query()->with(self::RELATIONS)->withCount('employees');
        SearchTerm::whereContains($base, ['name', 'code'], (string) $request->query('search', ''));

        $page = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::custom('organization', new OrganizationSubtreeFilter),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('parent', 'parent_id'),
            ])
            ->allowedSorts(['name', 'code', 'sort_order', 'created_at'])
            ->defaultSort('sort_order', 'name')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, DepartmentResource::class);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->departments->create($request->validated());

        return ApiResponse::data(new DepartmentResource($department->load(self::RELATIONS)), 201);
    }

    public function show(Department $department): JsonResponse
    {
        Gate::authorize('view', $department);

        return ApiResponse::data(new DepartmentResource($department->load(self::RELATIONS)->loadCount('employees')));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department = $this->departments->update($department, $request->validated());

        return ApiResponse::data(new DepartmentResource($department->load(self::RELATIONS)));
    }

    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);

        $this->departments->delete($department);

        return ApiResponse::message(__('messages.deleted'));
    }
}
