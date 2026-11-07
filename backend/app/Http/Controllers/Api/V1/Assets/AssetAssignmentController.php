<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use App\Services\Assets\AssetAssignments;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetAssignmentController extends Controller
{
    public function __construct(private readonly AssetAssignments $assignments) {}

    public function assign(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('assign', $asset);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:500']]);
        $employee = Employee::query()->withoutGlobalScopes()->whereNull('deleted_at')->find((int) $data['employee_id'])
            ?? throw ValidationException::withMessages(['employee_id' => __('messages.asset_employee_invalid')]);

        $this->assignments->assign($asset, $employee, $this->user($request), $data['note'] ?? null);

        return $this->respond($asset);
    }

    public function return(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('assign', $asset);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->assignments->return($asset, $this->user($request), $data['note'] ?? null);

        return $this->respond($asset);
    }

    public function history(Asset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);
        $rows = AssetAssignment::query()->where('asset_id', $asset->id)->with(['employee', 'assigner'])
            ->orderByDesc('assigned_at')->orderByDesc('id')->get()
            ->map(fn (AssetAssignment $a) => [
                'id' => $a->id,
                'employee' => $a->employee ? ['id' => $a->employee->id, 'name' => $a->employee->full_name] : null,
                'assigned_at' => $a->assigned_at->toIso8601String(),
                'assigned_by' => $a->assigner?->name,
                'note' => $a->note,
                'returned_at' => $a->returned_at?->toIso8601String(),
                'return_note' => $a->return_note,
            ]);

        return ApiResponse::data($rows->values());
    }

    /** Assets an employee holds now (employee card). */
    public function employeeAssets(Employee $employee): JsonResponse
    {
        Gate::authorize('viewAny', Asset::class);
        $assets = Asset::query()->where('holder_employee_id', $employee->id)->with(AssetResource::RELATIONS)->orderBy('inventory_number')->get();

        return ApiResponse::data(AssetResource::collection($assets));
    }

    /** The signed-in user's own assets, whatever their permissions. */
    public function mine(Request $request): JsonResponse
    {
        $employeeId = $this->user($request)->employee_id;
        $assets = $employeeId === null ? collect() : Asset::query()->withoutGlobalScopes()
            ->where('holder_employee_id', $employeeId)->with(AssetResource::RELATIONS)->orderBy('inventory_number')->get();

        return ApiResponse::data(AssetResource::collection($assets));
    }

    private function respond(Asset $asset): JsonResponse
    {
        return ApiResponse::data(new AssetResource($asset->refresh()->load(AssetResource::RELATIONS)));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
