<?php

namespace Domains\Quotation\States;

enum RfqInvitationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Sent, self::Acknowledged], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Declined, self::Expired, self::Cancelled], true);
    }
}
