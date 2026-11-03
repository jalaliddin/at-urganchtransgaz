<?php

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreItResponsibleRequest;
use App\Http\Resources\Organizations\ItResponsibleResource;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use App\Services\Organizations\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ItResponsibleController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    /**
     * The organization is in scope; its IT responsibles may belong to another
     * organization (e.g. central IT staff), so only the organization scope is lifted.
     */
    public function index(Organization $organization): JsonResponse
    {
        Gate::authorize('view', $organization);

        $rows = OrganizationItResponsible::query()
            ->where('organization_id', $organization->id)
            ->with(['employee' => fn ($q) => $q->withoutOrganizationScope()->with([
                'organization' => fn ($q2) => $q2->withoutOrganizationScope(),
            ])])
            ->orderByDesc('is_primary')->orderBy('id')
            ->get();

        return ApiResponse::data(ItResponsibleResource::collection($rows)->resolve(request()));
    }

    public function store(StoreItResponsibleRequest $request, Organization $organization): JsonResponse
    {
        $assignment = $this->organizations->assignItResponsible(
            $organization,
            Employee::query()->findOrFail($request->integer('employee_id')),
            $request->boolean('is_primary'),
            $request->string('assigned_at', now()->toDateString())->toString(),
            $request->input('notes'),
        );

        return ApiResponse::data(new ItResponsibleResource($assignment->load('employee.organization')), 201);
    }

    public function destroy(Organization $organization, int $employee): JsonResponse
    {
        Gate::authorize('manageItStaff', $organization);

        $assignment = OrganizationItResponsible::query()
            ->where('organization_id', $organization->id)
            ->where('employee_id', $employee)
            ->firstOrFail();

        $this->organizations->removeItResponsible($assignment);

        return ApiResponse::message(__('messages.it_responsible_removed'));
    }
}
