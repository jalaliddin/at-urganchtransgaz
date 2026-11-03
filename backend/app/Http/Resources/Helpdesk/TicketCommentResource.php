<?php

namespace App\Http\Resources\Helpdesk;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketComment */
class TicketCommentResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TicketComment $comment */
        $comment = $this->resource;

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'is_internal' => $comment->is_internal,
            'user' => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name] : null,
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->iso($comment->created_at),
        ];
    }
}
