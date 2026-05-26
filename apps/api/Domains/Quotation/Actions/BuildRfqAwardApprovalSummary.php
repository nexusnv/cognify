<?php

namespace Domains\Quotation\Actions;

use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Quotation\Models\RfqAwardRecommendation;

class BuildRfqAwardApprovalSummary
{
    public function handle(Tenant $tenant, RfqAwardRecommendation $recommendation): ?ApprovalInstance
    {
        return ApprovalInstance::query()
            ->with(['stages.tasks.assignee', 'tasks.assignee', 'tasks.decidedBy'])
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', RfqAwardRecommendation::class)
            ->where('subject_id', $recommendation->id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('started_at')
            ->first();
    }
}
