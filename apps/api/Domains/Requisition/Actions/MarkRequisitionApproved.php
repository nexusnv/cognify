<?php

namespace Domains\Requisition\Actions;

use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

class MarkRequisitionApproved
{
    public function handle(Requisition $requisition, ApprovalInstance $instance, User $actor): Requisition
    {
        $requisition->forceFill([
            'status' => RequisitionStatus::Approved,
            'approval_instance_id' => $instance->id,
            'approved_at' => now(),
            'approved_by_id' => $actor->id,
            'rejected_at' => null,
            'rejected_by_id' => null,
            'rejection_reason' => null,
        ])->save();

        return $requisition->refresh();
    }
}
