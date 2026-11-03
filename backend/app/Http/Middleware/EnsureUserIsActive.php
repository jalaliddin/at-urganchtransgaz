<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account is logged out on its very next request.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->getAttribute('is_active')) {
            Auth::guard('web')->logout();
            Auth::forgetGuards();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
