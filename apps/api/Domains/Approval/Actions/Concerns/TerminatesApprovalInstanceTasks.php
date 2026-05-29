<?php

namespace Domains\Approval\Actions\Concerns;

use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;
use Illuminate\Support\Facades\DB;

trait TerminatesApprovalInstanceTasks
{
    private function cancelRemainingTasks(ApprovalInstance $instance, ApprovalTask $decidingTask): void
    {
        ApprovalTask::query()
            ->where('approval_instance_id', $instance->id)
            ->whereKeyNot($decidingTask->id)
            ->whereIn('status', [
                ApprovalTaskStatus::Active,
                ApprovalTaskStatus::Blocked,
            ])
            ->update([
                'status' => ApprovalTaskStatus::Cancelled,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
    }
}
