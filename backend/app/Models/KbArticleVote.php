<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $article_id
 * @property int $user_id
 * @property bool $helpful
 */
class KbArticleVote extends Model
{
    protected $fillable = ['article_id', 'user_id', 'helpful'];

    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }
}
