<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::ReadyForReview, self::Cancelled], true);
    }
}
