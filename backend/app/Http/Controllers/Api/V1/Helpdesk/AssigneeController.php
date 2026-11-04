<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Active users who may work tickets of an organization (assignment picker). */
class AssigneeController extends Controller
{
    public function __invoke(Request $request, HelpdeskRecipients $recipients): JsonResponse
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

        $rows = $recipients->withPermission('helpdesk.process', $organizationId)
            ->map(fn (User $candidate): array => ['id' => $candidate->id, 'name' => $candidate->name])
            ->values();

        return ApiResponse::data($rows);
    }
}
