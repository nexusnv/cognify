<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }
}
