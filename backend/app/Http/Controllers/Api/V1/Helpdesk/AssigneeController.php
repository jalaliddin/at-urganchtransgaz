<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Active users who may work tickets of an organization (assignment picker). */
class AssigneeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->canAny(['helpdesk.assign', 'helpdesk.process'])) {
            throw new AccessDeniedHttpException;
        }
        $data = $request->validate(['organization_id' => ['required', 'integer']]);
        $organizationId = (int) $data['organization_id'];
        if (! $user->canAccessOrganization($organizationId)) {
            return ApiResponse::data([]);
        }

        $roleIds = DB::table('role_has_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.name', 'helpdesk.process')
            ->pluck('rp.role_id');

        // Candidates may live outside the caller's scope (e.g. central IT staff);
        // they are filtered by access to the ticket organization below.
        $candidates = User::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->orderBy('name')
            ->get(['id', 'name', 'organization_id']);

        $rows = $candidates
            ->filter(fn (User $candidate): bool => $candidate->canAccessOrganization($organizationId))
            ->map(fn (User $candidate): array => ['id' => $candidate->id, 'name' => $candidate->name])
            ->values();

        return ApiResponse::data($rows);
    }
}
