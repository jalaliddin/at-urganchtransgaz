<?php

namespace App\Http\Controllers\Api\V1\Health;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    /**
     * Liveness + dependency check. Deliberately reveals no versions or error details.
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            Cache::store()->get('health-check');
        } catch (Throwable $e) {
            Log::error('Health check failed', ['exception' => $e::class]);

            return ApiResponse::data(['status' => 'unavailable'], 503);
        }

        return ApiResponse::data(['status' => 'ok']);
    }
}
