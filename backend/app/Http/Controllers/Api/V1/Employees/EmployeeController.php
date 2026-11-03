<?php

namespace App\Http\Controllers\Api\V1\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Http\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use App\Services\Employees\EmployeeService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use App\Support\QueryFilters\OrganizationSubtreeFilter;
use App\Support\Search\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeController extends Controller
{
    private const RELATIONS = ['organization', 'department', 'user'];

    public function __construct(private readonly EmployeeService $employees) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $base = Employee::query()->with(self::RELATIONS);
        SearchTerm::whereContains(
            $base,
            ['last_name', 'first_name', 'middle_name', 'personnel_number', 'phone'],
            SearchTerm::fromRequest($request),
        );

        $page = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::custom('organization', new OrganizationSubtreeFilter),
                AllowedFilter::exact('department', 'department_id'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['last_name', 'personnel_number', 'status', 'created_at'])
            ->defaultSort('last_name', 'first_name')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, EmployeeResource::class);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employees->create($request->validated());

        return ApiResponse::data(new EmployeeResource($employee->load(self::RELATIONS)), 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        return ApiResponse::data(new EmployeeResource($employee->load(self::RELATIONS)));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employees->update($employee, $request->validated(), $request->user());

        return ApiResponse::data(new EmployeeResource($employee->load(self::RELATIONS)));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);

        $this->employees->delete($employee);

        return ApiResponse::message(__('messages.deleted'));
    }
}
