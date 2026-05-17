<?php

namespace Domains\Approval\States;

enum ApprovalPolicyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
