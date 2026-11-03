<?php

namespace App\Http\Resources\Helpdesk;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketAttachment */
class TicketAttachmentResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TicketAttachment $attachment */
        $attachment = $this->resource;
        $file = $attachment->file;

        return [
            'id' => $attachment->id,
            'comment_id' => $attachment->comment_id,
            'file' => [
                'uuid' => $file->uuid,
                'name' => $file->original_name,
                'mime' => $file->mime,
                'size' => $file->size,
                'is_image' => $file->isImage(),
            ],
            'user' => $attachment->user ? ['id' => $attachment->user->id, 'name' => $attachment->user->name] : null,
            'created_at' => $this->iso($attachment->created_at),
        ];
    }
}
