<?php

namespace Domains\Quotation\States;

enum RfqAwardRecommendationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case ApprovalRouted = 'approval_routed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Withdrawn = 'withdrawn';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PendingApproval;
    }

    public function isTerminalForAwardApproval(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::ChangesRequested, self::Withdrawn], true);
    }
}
