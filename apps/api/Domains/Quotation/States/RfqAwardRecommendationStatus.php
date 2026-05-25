<?php

namespace Domains\Quotation\States;

enum RfqAwardRecommendationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Withdrawn = 'withdrawn';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PendingApproval;
    }
}
