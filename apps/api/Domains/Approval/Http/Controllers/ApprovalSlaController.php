<?php

namespace Domains\Approval\Http\Controllers;

use App\Auth\TenantRole;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Http\Resources\ApprovalSummaryResource;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Queries\ApprovalSlaSummaryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalSlaController extends Controller
{
    public function __construct(
        private readonly ApprovalSlaSummaryQuery $summaryQuery,
    ) {}

    public function summary(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorizeMonitoring($request, $currentTenant);

        return response()->json([
            'data' => $this->summaryQuery->handle($tenant),
        ]);
    }

    public function showInstance(Request $request, CurrentTenant $currentTenant, int $approvalInstance): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorizeMonitoring($request, $currentTenant);

        $instance = ApprovalInstance::query()
            ->with(['stages.tasks.assignee', 'tasks.assignee', 'tasks.decidedBy'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($approvalInstance);

        return response()->json([
            'data' => (new ApprovalSummaryResource($instance))->resolve(),
        ]);
    }

    private function authorizeMonitoring(Request $request, CurrentTenant $currentTenant): void
    {
        $role = $currentTenant->roleFor($request->user());

        abort_unless(in_array($role, [TenantRole::Admin->value, TenantRole::Buyer->value], true), 403);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
