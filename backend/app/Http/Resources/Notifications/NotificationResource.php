<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\Concerns\FormatsDates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;
        $data = (array) $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? null,
            'title_key' => $data['title_key'] ?? null,
            'params' => $data['params'] ?? [],
            'url' => $data['url'] ?? null,
            'read_at' => $this->iso($notification->read_at),
            'created_at' => $this->iso($notification->created_at),
        ];
    }
}
