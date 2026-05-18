<?php

namespace Domains\Approval\States;

enum ApprovalTaskStatus: string
{
    case Active = 'active';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Cancelled = 'cancelled';
}
