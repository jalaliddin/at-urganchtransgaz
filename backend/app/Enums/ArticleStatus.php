<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('enums.article_status.'.$this->value);
    }
}
