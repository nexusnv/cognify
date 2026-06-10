<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case ChangePending = 'change_pending';
    case Approved = 'approved';
    case Issued = 'issued';
    case Acknowledged = 'acknowledged';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Issued, self::Acknowledged, self::Rejected, self::Cancelled], true);
    }
}
