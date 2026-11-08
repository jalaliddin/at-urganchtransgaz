<?php

namespace App\Http\Middleware;

use App\Services\Software\AgentService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Agent API guard: a valid, unrevoked bearer token of an active agent; the agent is put on the request. */
class AuthenticateAgent
{
    public function __construct(private readonly AgentService $agents) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->agents->authenticate((string) $request->bearerToken());
        if ($token === null) {
            return ApiResponse::error(__('messages.unauthenticated'), 'unauthenticated', 401);
        }
        $token->forceFill(['last_used_at' => CarbonImmutable::now()])->save();
        $request->attributes->set('agent', $token->agent);

        return $next($request);
    }
}
