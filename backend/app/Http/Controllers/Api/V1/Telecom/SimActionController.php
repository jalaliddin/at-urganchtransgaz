<?php

namespace App\Http\Controllers\Api\V1\Telecom;

use App\Enums\SimStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Telecom\SimResource;
use App\Models\Employee;
use App\Models\SimCard;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Sim\SimLifecycle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * SIM card actions. Holder changes (assign, return, block, unblock) — sim.assign or sim.manage; moves between
 * organizations, cancellation and card replacement — sim.manage. Route binding applies the organization scope.
 */
class SimActionController extends Controller
{
    public function __construct(private readonly SimLifecycle $lifecycle, private readonly SimCardController $cards) {}

    public function assign(Request $request, SimCard $simCard): JsonResponse
    {
        $this->authorizeHolderChange();
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'assigned_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->done($request, $this->lifecycle->assign($simCard, $data, $this->user($request)));
    }

    public function return(Request $request, SimCard $simCard): JsonResponse
    {
        $this->authorizeHolderChange();
        $data = $request->validate(['returned_at' => ['nullable', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:2000']]);

        return $this->done($request, $this->lifecycle->return($simCard, $data['returned_at'] ?? null, $data['note'] ?? null, $this->user($request)));
    }

    public function block(Request $request, SimCard $simCard): JsonResponse
    {
        $this->authorizeHolderChange();
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->done($request, $this->lifecycle->block($simCard, $data['reason'] ?? null, $this->user($request)));
    }

    public function unblock(Request $request, SimCard $simCard): JsonResponse
    {
        $this->authorizeHolderChange();

        return $this->done($request, $this->lifecycle->unblock($simCard, $this->user($request)));
    }

    public function transfer(Request $request, SimCard $simCard): JsonResponse
    {
        Gate::authorize('sim.manage');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->done($request, $this->lifecycle->transfer($simCard, (int) $data['organization_id'], $data['note'] ?? null, $this->user($request)));
    }

    public function cancel(Request $request, SimCard $simCard): JsonResponse
    {
        Gate::authorize('sim.manage');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->done($request, $this->lifecycle->cancel($simCard, $data['reason'] ?? null, $this->user($request)));
    }

    public function replace(Request $request, SimCard $simCard): JsonResponse
    {
        Gate::authorize('sim.manage');
        $data = $request->validate(['iccid' => ['required', 'string', 'max:40'], 'reason' => ['nullable', 'string', 'max:500']]);

        return $this->done($request, $this->lifecycle->replace($simCard, $data['iccid'], $data['reason'] ?? null, $this->user($request)));
    }

    /** Cards an employee holds now (route binding applies the employee's organization scope). */
    public function employeeCards(Employee $employee): JsonResponse
    {
        Gate::authorize('sim.use');
        $cards = SimCard::query()->with(['provider', 'organization', 'department', 'employee'])->where('employee_id', $employee->id)
            ->whereIn('status', [SimStatus::Active->value, SimStatus::Blocked->value])->orderBy('phone_number')->get();

        return ApiResponse::data($cards->map(fn (SimCard $c) => SimResource::card($c))->values());
    }

    private function authorizeHolderChange(): void
    {
        abort_unless(request()->user()?->canAny(['sim.assign', 'sim.manage']), 403);
    }

    private function done(Request $request, SimCard $card): JsonResponse
    {
        return $this->cards->show($card);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
