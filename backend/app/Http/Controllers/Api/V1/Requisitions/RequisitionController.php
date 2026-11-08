<?php

namespace App\Http\Controllers\Api\V1\Requisitions;

use App\Enums\RequisitionStatus as S;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requisitions\RequisitionRequest;
use App\Http\Resources\Requisitions\RequisitionResource;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Requisitions\RequisitionService;
use App\Services\Requisitions\RequisitionWorkflow;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RequisitionController extends Controller
{
    public function __construct(private readonly RequisitionService $service, private readonly RequisitionWorkflow $workflow) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->canAny(['requisitions.view', 'requisitions.create']), 403);
        $query = Requisition::query()->visibleTo($user)->with(RequisitionResource::ROW_RELATIONS)->latest('updated_at')->latest('id');

        match ($request->input('filter.tab')) {
            'mine' => $query->where('requester_id', $user->id),
            'action' => $this->awaitingAction($query, $user),
            default => null,
        };
        if ($request->filled('filter.status')) {
            $query->whereIn('status', explode(',', (string) $request->input('filter.status')));
        }
        if ($request->filled('filter.organization')) {
            $query->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]);
        }
        $search = trim((string) $request->input('filter.search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        }
        $page = $query->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (Requisition $r) => RequisitionResource::row($r));
    }

    public function store(RequisitionRequest $request): JsonResponse
    {
        Gate::authorize('requisitions.create');
        $user = $this->user($request);
        $requisition = $this->service->create($request->safe()->except('items'), $request->items() ?? [], $user);

        return $this->detail($requisition, $user, 201);
    }

    public function show(Request $request, Requisition $requisition): JsonResponse
    {
        return $this->detail($requisition, $this->user($request));
    }

    public function update(RequisitionRequest $request, Requisition $requisition): JsonResponse
    {
        $user = $this->user($request);
        $this->service->update($requisition, $request->safe()->except('items'), $request->items(), $user);

        return $this->detail($requisition, $user);
    }

    public function destroy(Request $request, Requisition $requisition): JsonResponse
    {
        $this->service->delete($requisition, $this->user($request));

        return ApiResponse::message(__('messages.deleted'));
    }

    public function detail(Requisition $requisition, User $user, int $status = 200): JsonResponse
    {
        $requisition->refresh()->load(RequisitionResource::DETAIL_RELATIONS);

        return ApiResponse::data(RequisitionResource::detail($requisition, $this->workflow->abilities($requisition, $user)), $status);
    }

    /**
     * Requisitions waiting for something this user may do now (mirrors RequisitionWorkflow roles).
     *
     * @param  Builder<Requisition>  $query
     */
    private function awaitingAction(Builder $query, User $user): void
    {
        $own = fn (Builder $q) => $q->where('requester_id', $user->id)
            ->when($user->can('requisitions.create') && $user->organization_id !== null, fn ($w) => $w->orWhere('organization_id', $user->organization_id));

        $query->where(function (Builder $q) use ($user, $own): void {
            $q->whereRaw('1 = 0');
            if ($user->can('requisitions.approve_org')) {
                $q->orWhere(fn ($w) => $w->where('status', S::OrgApproval)->where('requester_id', '!=', $user->id));
            }
            if ($user->can('requisitions.review')) {
                $q->orWhereIn('status', [S::Submitted->value, S::CentralReview->value]);
            } elseif ($user->can('requisitions.approve')) {
                $q->orWhere(fn ($w) => $w->whereIn('status', [S::Submitted->value, S::CentralReview->value])->where('requester_id', '!=', $user->id));
            }
            if ($user->can('requisitions.fulfill')) {
                $q->orWhereIn('status', [S::Approved->value, S::ReadyForAllocation->value, S::PartiallyFulfilled->value]);
            }
            if ($user->canAny(['requisitions.approve', 'requisitions.fulfill'])) {
                $q->orWhere('status', S::Received);
            }
            $q->orWhere(fn ($w) => $w->whereIn('status', [S::InfoRequested->value, S::Fulfilled->value])->where($own));
        });
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
