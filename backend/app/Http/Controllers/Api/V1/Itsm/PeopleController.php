<?php

namespace App\Http\Controllers\Api\V1\Itsm;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Active users who may be responsible for a problem, implement a change or perform maintenance in an organization
 * (the same rules the services enforce).
 */
class PeopleController extends Controller
{
    /** @var array<string, array{gate: string, permissions: list<string>}> */
    private const KINDS = [
        'problem' => ['gate' => 'problems.manage', 'permissions' => ['problems.manage']],
        'change' => ['gate' => 'changes.create', 'permissions' => ['changes.create', 'changes.manage']],
        'maintenance' => ['gate' => 'maintenance.manage', 'permissions' => ['maintenance.perform']],
    ];

    public function __invoke(Request $request, HelpdeskRecipients $recipients): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate(['for' => ['required', Rule::in(array_keys(self::KINDS))], 'organization_id' => ['required', 'integer']]);
        $kind = self::KINDS[$data['for']];
        abort_unless($user->can($kind['gate']), 403);
        $organizationId = (int) $data['organization_id'];
        if (! $user->canAccessOrganization($organizationId)) {
            return ApiResponse::data([]);
        }

        $people = collect($kind['permissions'])->flatMap(fn (string $p) => $recipients->withPermission($p, $organizationId))
            ->unique('id')->sortBy('name')->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values();

        return ApiResponse::data($people);
    }
}
