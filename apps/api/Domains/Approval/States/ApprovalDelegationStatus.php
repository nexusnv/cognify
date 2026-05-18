<?php

namespace Domains\Approval\States;

enum ApprovalDelegationStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
