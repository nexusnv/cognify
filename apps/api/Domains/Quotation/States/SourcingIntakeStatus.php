<?php

namespace Domains\Quotation\States;

enum SourcingIntakeStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case ClarificationRequested = 'clarification_requested';
    case ReadyForRfq = 'ready_for_rfq';
    case DirectAwardRecorded = 'direct_award_recorded';
    case Closed = 'closed';

    public function isTerminalForEditing(): bool
    {
        return in_array($this, [self::ReadyForRfq, self::DirectAwardRecorded, self::Closed], true);
    }
}
