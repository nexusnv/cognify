<?php

namespace Domains\Requisition\Actions;

use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use InvalidArgumentException;

class MarkRequisitionPendingApproval
{
    public function handle(Requisition $requisition, ApprovalInstance $instance, User $actor): Requisition
    {
        if ((int) $requisition->tenant_id !== (int) $instance->tenant_id) {
            throw new InvalidArgumentException('Requisition and approval instance tenant mismatch.');
        }

        $requisition->forceFill([
            'status' => RequisitionStatus::PendingApproval,
            'approval_instance_id' => $instance->id,
        ])->save();

        return $requisition->refresh();
    }
}
