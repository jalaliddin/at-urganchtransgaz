<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Audit\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('audit.view');

        $base = AuditLog::query()->visibleTo($this->user($request))->with(AuditLogResource::RELATIONS);

        $page = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::exact('event'),
                AllowedFilter::exact('user', 'user_id'),
                AllowedFilter::exact('auditable_type'),
                AllowedFilter::exact('auditable_id'),
                AllowedFilter::callback('organization', fn (Builder $q, mixed $value) => $this->subtree($q, $value)),
                AllowedFilter::callback('created_from', fn (Builder $q, mixed $value) => $q->where('created_at', '>=', $this->tashkentDay($value)->startOfDay()->utc())),
                AllowedFilter::callback('created_to', fn (Builder $q, mixed $value) => $q->where('created_at', '<=', $this->tashkentDay($value)->endOfDay()->utc())),
            ])
            ->allowedSorts(['created_at'])
            ->defaultSort('-created_at', '-id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($page, AuditLogResource::class);
    }

    public function show(Request $request, int $auditLog): JsonResponse
    {
        Gate::authorize('audit.view');

        $log = AuditLog::query()->visibleTo($this->user($request))->with(AuditLogResource::RELATIONS)->findOrFail($auditLog);

        return ApiResponse::data(new AuditLogResource($log));
    }

    /** @param Builder<AuditLog> $query */
    private function subtree(Builder $query, mixed $value): void
    {
        $path = DB::table('organizations')->where('id', (int) $value)->value('path');
        if (! is_string($path)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('organization_id', DB::table('organizations')->select('id')->where('path', 'like', $path.'%'));
    }

    /** A YYYY-MM-DD filter value is a calendar day in the users' timezone (Asia/Tashkent). */
    private function tashkentDay(mixed $value): CarbonImmutable
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', (string) $value, (string) config('app.display_timezone'));
        } catch (Throwable) {
            $day = null;
        }

        if (! $day instanceof CarbonImmutable || $day->format('Y-m-d') !== (string) $value) {
            throw new BadRequestHttpException('Invalid date filter.');
        }

        return $day;
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
