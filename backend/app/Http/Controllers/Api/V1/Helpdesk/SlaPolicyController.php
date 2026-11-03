<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\SlaPolicyRequest;
use App\Http\Resources\Helpdesk\SlaPolicyResource;
use App\Models\TicketSlaPolicy;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SlaPolicyController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('helpdesk.use');

        $policies = TicketSlaPolicy::query()->with('category')
            ->orderByRaw('category_id is not null')->orderBy('category_id')->orderBy('priority')
            ->get();

        return ApiResponse::data(SlaPolicyResource::collection($policies));
    }

    public function store(SlaPolicyRequest $request): JsonResponse
    {
        $policy = TicketSlaPolicy::create($request->validated());

        return ApiResponse::data(new SlaPolicyResource($policy->load('category')), 201);
    }

    public function update(SlaPolicyRequest $request, TicketSlaPolicy $ticketSlaPolicy): JsonResponse
    {
        $ticketSlaPolicy->update($request->validated());

        return ApiResponse::data(new SlaPolicyResource($ticketSlaPolicy->load('category')));
    }

    public function destroy(TicketSlaPolicy $ticketSlaPolicy): JsonResponse
    {
        Gate::authorize('helpdesk.manage');
        $ticketSlaPolicy->delete();

        return ApiResponse::message(__('messages.deleted'));
    }
}
