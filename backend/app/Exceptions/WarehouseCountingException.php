<?php

namespace App\Exceptions;

/** The warehouse has an open stock count; its stock is frozen until the count is closed or cancelled. */
class WarehouseCountingException extends DomainConflictException
{
    public function __construct()
    {
        parent::__construct(__('messages.warehouse_counting'));
    }

    public function errorCode(): string
    {
        return 'warehouse_counting';
    }
}
