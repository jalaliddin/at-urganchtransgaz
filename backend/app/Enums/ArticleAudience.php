<?php

namespace App\Enums;

enum ArticleAudience: string
{
    case All = 'all';
    case It = 'it';

    public function label(): string
    {
        return __('enums.article_audience.'.$this->value);
    }
}
