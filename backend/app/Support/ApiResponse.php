<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the API's uniform JSON envelopes (see docs/API.md §2).
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function data(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $extra
     */
    public static function error(string $message, string $code, int $status, array $errors = [], array $extra = []): JsonResponse
    {
        $payload = ['message' => $message, 'code' => $code];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return new JsonResponse($payload + $extra, $status);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $page, string $resource): JsonResponse
    {
        return self::data(
            $resource::collection($page->getCollection())->resolve(request()),
            meta: [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        );
    }
}
