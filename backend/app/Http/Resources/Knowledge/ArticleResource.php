<?php

namespace App\Http\Resources\Knowledge;

use App\Http\Resources\Warehouse\StockResource;
use App\Models\KbArticle;
use App\Models\KbArticleAttachment;

/** JSON shapes of the knowledge base. */
final class ArticleResource
{
    /** @return array<string, mixed> */
    public static function row(KbArticle $a): array
    {
        return [
            'id' => $a->id,
            'number' => $a->number,
            'title' => $a->title,
            'type' => $a->type->value,
            'category' => $a->category === null ? null : ['id' => $a->category->id, 'name' => $a->category->localizedName()],
            'summary' => $a->summary,
            'audience' => $a->audience->value,
            'status' => $a->status->value,
            'published_at' => $a->published_at?->toIso8601String(),
            'updated_at' => $a->updated_at->toIso8601String(),
            'author' => $a->author === null ? null : ['id' => $a->author->id, 'name' => $a->author->name],
            'views' => $a->views,
            'helpful' => (int) ($a->getAttribute('helpful_count') ?? 0),
            'not_helpful' => (int) ($a->getAttribute('not_helpful_count') ?? 0),
        ];
    }

    /**
     * @param  array{edit: bool, delete: bool}  $can
     * @return array<string, mixed>
     */
    public static function detail(KbArticle $a, ?bool $myVote, array $can): array
    {
        return [
            ...self::row($a),
            'body' => $a->body,
            'attachments' => $a->attachments->map(fn (KbArticleAttachment $f) => ['id' => $f->id, 'file' => StockResource::file($f->file)])->values()->all(),
            'source_ticket' => $a->sourceTicket === null ? null
                : ['id' => $a->sourceTicket->id, 'number' => $a->sourceTicket->number, 'title' => $a->sourceTicket->title],
            'my_vote' => $myVote,
            'can' => $can,
        ];
    }
}
