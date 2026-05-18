<?php

namespace Domains\Requisition\Actions;

use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

class MarkRequisitionPendingApproval
{
    public function handle(Requisition $requisition, ApprovalInstance $instance, User $actor): Requisition
    {
        $requisition->forceFill([
            'status' => RequisitionStatus::PendingApproval,
            'approval_instance_id' => $instance->id,
        ])->save();

        return $requisition->refresh();
    }
}
