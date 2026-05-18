<?php

namespace Domains\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\PreviewApprovalPolicy;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Http\Resources\ApprovalPreviewResource;
use Domains\Approval\Models\ApprovalPolicyVersion;
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
