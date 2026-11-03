<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notifications\NotificationResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user's own in-app notifications. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->user($request)->notifications()->latest();
        if (filter_var($request->input('filter.unread'), FILTER_VALIDATE_BOOL)) {
            $query->whereNull('read_at');
        }

        return ApiResponse::paginated($query->paginate(Pagination::perPage($request)), NotificationResource::class);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::data(['count' => $this->user($request)->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $this->user($request)->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return ApiResponse::message(__('messages.notifications_marked_read'));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::message(__('messages.notifications_marked_read'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
