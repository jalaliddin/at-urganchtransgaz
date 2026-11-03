<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until a required password change is done, only these routes are reachable.
 */
class EnsurePasswordIsChanged
{
    public const ALLOWED_ROUTES = ['auth.me', 'auth.me.update', 'auth.logout', 'auth.password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && ($user->must_change_password || $user->passwordExpired())
            && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return ApiResponse::error(__('auth.password_change_required'), 'password_change_required', 403);
        }

        return $next($request);
    }
}
