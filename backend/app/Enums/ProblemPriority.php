<?php

namespace App\Enums;

enum ProblemPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return __('enums.problem_priority.'.$this->value);
    }
}
