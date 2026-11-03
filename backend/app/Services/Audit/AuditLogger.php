<?php

namespace App\Services\Audit;

use App\Enums\AuditEvent;
use App\Http\Middleware\AssignRequestId;
use App\Models\AuditLog;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes audit rows. HTTP context (IP, user agent, method, path, request id) is
 * attached only when the current request went through AssignRequestId.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(
        AuditEvent $event,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?User $actor = null,
        ?int $organizationId = null,
        ?string $userLabel = null,
    ): AuditLog {
        $actor ??= Auth::hasUser() && Auth::user() instanceof User ? Auth::user() : null;

        return AuditLog::create([
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'user_id' => $actor?->id,
            'user_label' => $userLabel ?? $actor?->username,
            'organization_id' => $organizationId ?? $this->organizationOf($subject),
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            ...$this->requestContext(),
        ]);
    }

    private function organizationOf(?Model $subject): ?int
    {
        if ($subject === null || ! in_array(BelongsToOrganization::class, class_uses_recursive($subject), true)) {
            return null;
        }

        /** @var callable(): string $column */
        $column = [$subject::class, 'organizationColumn'];
        $value = $subject->getAttribute($column());

        return $value === null ? null : (int) $value;
    }

    /** @return array<string, string|null> */
    private function requestContext(): array
    {
        $request = request();
        $requestId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (! is_string($requestId)) {
            return [];
        }

        return [
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'http_method' => $request->method(),
            'url' => Str::limit('/'.ltrim($request->path(), '/'), 500, ''),
            'request_id' => $requestId,
        ];
    }
}
