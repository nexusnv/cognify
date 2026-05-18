<?php

namespace Domains\Approval\States;

enum ApprovalStageStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
