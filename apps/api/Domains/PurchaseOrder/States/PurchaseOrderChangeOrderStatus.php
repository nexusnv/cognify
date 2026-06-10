<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderChangeOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::ChangesRequested], true);
    }
}
