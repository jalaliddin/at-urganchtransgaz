<?php

namespace App\Http\Resources\Audit;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    use FormatsDates;

    public const RELATIONS = ['user', 'organization'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'event' => $log->event->value,
            'event_label' => $log->event->label(),
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'user' => $log->user ? ['id' => $log->user->id, 'username' => $log->user->username] : null,
            'user_label' => $log->user_label,
            'organization' => $log->organization ? ['id' => $log->organization->id, 'name' => $log->organization->name] : null,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->getAttribute('user_agent'),
            'http_method' => $log->http_method,
            'url' => $log->url,
            'request_id' => $log->request_id,
            'created_at' => $this->iso($log->created_at),
        ];
    }
}
