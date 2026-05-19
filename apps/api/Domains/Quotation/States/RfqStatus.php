<?php

namespace Domains\Quotation\States;

enum RfqStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
