<?php

namespace App\Enums;

enum DocumentType: string
{
    case Contract = 'contract';
    case Act = 'act';
    case Requisition = 'requisition';
    case Invoice = 'invoice';
    case TechnicalPassport = 'technical_passport';
    case Warranty = 'warranty';
    case Order = 'order';
    case Letter = 'letter';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.document_type.'.$this->value);
    }
}
