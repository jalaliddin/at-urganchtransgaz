<?php

namespace App\Exceptions;

use RuntimeException;

/** A decrease would take a lot below zero (rendered as 422 "insufficient_stock" with the shortages). */
class InsufficientStockException extends RuntimeException
{
    /**
     * @param  list<array{material_id: int, material: string, batch: string|null, available: string, requested: string}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct(__('messages.insufficient_stock'));
    }
}
