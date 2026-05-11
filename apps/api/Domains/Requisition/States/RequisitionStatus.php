<?php

namespace Domains\Requisition\States;

enum RequisitionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
}
