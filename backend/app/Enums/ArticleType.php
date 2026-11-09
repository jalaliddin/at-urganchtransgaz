<?php

namespace App\Enums;

enum ArticleType: string
{
    case Faq = 'faq';
    case Guide = 'guide';
    case Solution = 'solution';

    public function label(): string
    {
        return __('enums.article_type.'.$this->value);
    }
}
