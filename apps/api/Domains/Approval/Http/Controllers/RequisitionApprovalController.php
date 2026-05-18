<?php

namespace Domains\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\PreviewApprovalPolicy;
use Domains\Approval\Actions\RouteRequisitionForApproval;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Http\Resources\ApprovalSummaryResource;
use Domains\Approval\Http\Resources\ApprovalPreviewResource;
use Domains\Approval\Http\Resources\ApprovalTaskResource;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequisitionApprovalController extends Controller
{
    public function preview(
        Request $request,
        CurrentTenant $currentTenant,
        PreviewApprovalPolicy $action,
        int $requisition,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($tenant, $requisition);

        $this->authorize('view', $requisition);

        $context = ApprovalContextData::fromRequisition($requisition);
        $candidates = $this->tenantPolicyCandidates($tenant);

        return (new ApprovalPreviewResource(
            $action->handle($tenant, $request->user(), $context, $candidates),
        ))->response();
    }

    public function route(
        CurrentTenant $currentTenant,
        RouteRequisitionForApproval $action,
        int $requisition,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($tenant, $requisition);

        $this->authorize('routeApproval', $requisition);

        $instance = $action->handle($tenant, request()->user(), $requisition);

        return response()->json([
            'data' => [
                'instance' => (new ApprovalSummaryResource($instance->load(['stages', 'tasks.assignee', 'tasks.decidedBy'])))->resolve(),
                'tasks' => ApprovalTaskResource::collection($instance->tasks()->with(['assignee', 'originalAssignee', 'decidedBy', 'stage', 'instance', 'subject.requester', 'subject.lineItems'])->get())->resolve(),
            ],
        ]);
    }

    public function summary(CurrentTenant $currentTenant, int $requisition): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($tenant, $requisition);

        $this->authorize('view', $requisition);

        $instance = ApprovalInstance::query()
            ->with(['stages', 'tasks.assignee', 'tasks.decidedBy'])
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', Requisition::class)
            ->where('subject_id', $requisition->id)
            ->whereIn('status', [
                ApprovalInstanceStatus::Active,
                ApprovalInstanceStatus::Approved,
                ApprovalInstanceStatus::Rejected,
                ApprovalInstanceStatus::ChangesRequested,
            ])
            ->latest('id')
            ->first();

        return response()->json([
            'data' => $instance !== null ? (new ApprovalSummaryResource($instance))->resolve() : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tenantPolicyCandidates(Tenant $tenant): array
    {
        return ApprovalPolicyVersion::query()
            ->with('policy')
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', 'requisition')
            ->where('status', 'published')
            ->orderByDesc('priority')
            ->orderByDesc('version_number')
            ->get()
            ->map(function (ApprovalPolicyVersion $version): array {
                return [
                    'matchedPolicy' => [
                        'id' => (string) $version->approval_policy_id,
                        'tenantId' => (string) $version->tenant_id,
                        'name' => $version->policy?->name ?? 'Approval policy',
                        'subjectType' => $version->subject_type,
                        'status' => $version->policy?->status->value ?? 'draft',
                    ],
                    'matchedVersion' => [
                        'id' => (string) $version->id,
                        'tenantId' => (string) $version->tenant_id,
                        'policyId' => (string) $version->approval_policy_id,
                        'versionNumber' => $version->version_number,
                        'status' => $version->status->value,
                        'priority' => $version->priority,
                        'rules' => $version->rules ?? [],
                        'routeTemplate' => $version->route_template ?? ['stages' => []],
                        'slaRules' => $version->sla_rules ?? [],
                    ],
                    'priority' => $version->priority,
                    'rules' => $version->rules ?? [],
                    'routeTemplate' => $version->route_template ?? ['stages' => []],
                    'slaRules' => $version->sla_rules ?? [],
                ];
            })
            ->all();
    }

    private function findTenantRequisition(Tenant $tenant, int $id): Requisition
    {
        return Requisition::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id)
            ->load(['requester', 'lineItems']);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
