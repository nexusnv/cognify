<?php

namespace Domains\Approval\States;

enum ApprovalPolicyVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';
}
