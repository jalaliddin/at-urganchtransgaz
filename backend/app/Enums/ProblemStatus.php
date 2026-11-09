<?php

namespace App\Enums;

enum ProblemStatus: string
{
    case New = 'new';
    case Investigating = 'investigating';
    case KnownError = 'known_error';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /** Still being worked on (resolved and closed problems are not). */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Investigating, self::KnownError], true);
    }

    /** @return list<self> statuses reachable from this one (design R1). */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Investigating, self::KnownError, self::Resolved, self::Closed],
            self::Investigating => [self::KnownError, self::Resolved, self::Closed],
            self::KnownError => [self::Investigating, self::Resolved, self::Closed],
            self::Resolved => [self::Investigating, self::Closed],
            self::Closed => [],
        };
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::New->value, self::Investigating->value, self::KnownError->value];
    }

    public function label(): string
    {
        return __('enums.problem_status.'.$this->value);
    }
}
