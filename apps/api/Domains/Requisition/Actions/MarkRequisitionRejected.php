<?php

namespace Domains\Requisition\Actions;

use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use InvalidArgumentException;

class MarkRequisitionRejected
{
    public function handle(Requisition $requisition, ApprovalInstance $instance, User $actor, string $reason): Requisition
    {
        if ((int) $requisition->tenant_id !== (int) $instance->tenant_id) {
            throw new InvalidArgumentException('Requisition and approval instance tenant mismatch.');
        }

        $requisition->forceFill([
            'status' => RequisitionStatus::Rejected,
            'approval_instance_id' => $instance->id,
            'rejected_at' => now(),
            'rejected_by_id' => $actor->id,
            'rejection_reason' => $reason,
            'approved_at' => null,
            'approved_by_id' => null,
        ])->save();

        return $requisition->refresh();
    }
}
