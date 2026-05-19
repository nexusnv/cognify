<?php

namespace Domains\Approval\States;

enum ApprovalStageStatus: string
{
    case Blocked = 'blocked';
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
