<?php

namespace App\Exceptions;

use App\Http\Middleware\AssignRequestId;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception on API requests as the uniform error envelope
 * {message, code, errors?} (docs/API.md §2). Receives exceptions after Laravel's
 * prepareException(), so ModelNotFound/Authorization/TokenMismatch arrive as HTTP exceptions.
 */
final class ApiExceptionRenderer
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        419 => 'csrf_token_mismatch',
        422 => 'validation_error',
        429 => 'too_many_requests',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                __('messages.validation_failed'), 'validation_error', 422, $e->errors(),
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                __('messages.unauthenticated'), 'unauthenticated', 401,
            ),
            $e instanceof InvalidTransitionException => ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : __('messages.invalid_transition'), 'invalid_transition', 422,
            ),
            $e instanceof DomainConflictException => ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : __('messages.conflict'), 'conflict', 409,
            ),
            $e instanceof HttpExceptionInterface => $this->http($e),
            default => $this->serverError($e, $request),
        };
    }

    private function http(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $code = self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'http_error');

        $message = match ($status) {
            403 => $this->customForbiddenMessage($e) ?? __('messages.forbidden'),
            default => __('messages.'.($status >= 500 ? 'server_error' : $code)),
        };

        $response = ApiResponse::error($message, $code, $status);
        $response->headers->add($e->getHeaders());

        return $response;
    }

    /** Policies may deny with an Uzbek message; Laravel's English default is replaced. */
    private function customForbiddenMessage(HttpExceptionInterface $e): ?string
    {
        $message = $e->getMessage();

        return $message === '' || $message === 'This action is unauthorized.' ? null : $message;
    }

    private function serverError(Throwable $e, Request $request): JsonResponse
    {
        $extra = ['request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)];

        if (config('app.debug')) {
            $extra['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ];
        }

        return ApiResponse::error(__('messages.server_error'), 'server_error', 500, [], $extra);
    }
}
