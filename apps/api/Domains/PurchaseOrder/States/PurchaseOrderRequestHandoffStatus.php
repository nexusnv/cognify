<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderRequestHandoffStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Exported = 'exported';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Cancelled;
    }
}
