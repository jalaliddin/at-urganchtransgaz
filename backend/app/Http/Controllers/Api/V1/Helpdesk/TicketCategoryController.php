<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\TicketCategoryRequest;
use App\Http\Resources\Helpdesk\TicketCategoryResource;
use App\Models\TicketCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TicketCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('helpdesk.use');

        $categories = TicketCategory::query()
            ->when($request->boolean('filter.active'), fn ($q) => $q->active())
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return ApiResponse::data(TicketCategoryResource::collection($categories));
    }

    public function store(TicketCategoryRequest $request): JsonResponse
    {
        $category = TicketCategory::create($request->validated());

        return ApiResponse::data(new TicketCategoryResource($category->refresh()), 201);
    }

    public function update(TicketCategoryRequest $request, TicketCategory $ticketCategory): JsonResponse
    {
        $ticketCategory->update($request->validated());

        return ApiResponse::data(new TicketCategoryResource($ticketCategory));
    }

    public function destroy(TicketCategory $ticketCategory): JsonResponse
    {
        Gate::authorize('helpdesk.manage');

        if (DB::table('helpdesk_tickets')->where('category_id', $ticketCategory->id)->exists()) {
            throw new DomainConflictException(__('messages.category_in_use'));
        }
        $ticketCategory->delete();

        return ApiResponse::message(__('messages.deleted'));
    }
}
