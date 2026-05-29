<?php

namespace Domains\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\SaveApprovalPolicyDraft;
use Domains\Approval\Actions\PreviewApprovalPolicy;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Http\Requests\PreviewApprovalPolicyRequest;
use Domains\Approval\Http\Requests\StoreApprovalPolicyRequest;
use Domains\Approval\Http\Requests\UpdateApprovalPolicyRequest;
use Domains\Approval\Http\Resources\ApprovalPreviewResource;
use Domains\Approval\Http\Resources\ApprovalPolicyResource;
use Domains\Approval\Models\ApprovalPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalPolicyController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorizeAdmin($request, $currentTenant);
        $tenant = $this->tenantOrAbort($currentTenant);

        $policies = ApprovalPolicy::query()
            ->with('versions')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ApprovalPolicyResource::collection($policies)->resolve(),
        ]);
    }

    public function store(StoreApprovalPolicyRequest $request, CurrentTenant $currentTenant, SaveApprovalPolicyDraft $action): JsonResponse
    {
        $policy = $action->handle($this->tenantOrAbort($currentTenant), $request->user(), $request->validated());

        return (new ApprovalPolicyResource($policy))->response()->setStatusCode(201);
    }

    public function show(Request $request, CurrentTenant $currentTenant, int $approvalPolicy): ApprovalPolicyResource
    {
        $this->authorizeAdmin($request, $currentTenant);

        return new ApprovalPolicyResource($this->findTenantPolicy($currentTenant, $approvalPolicy)->load('versions'));
    }

    public function update(UpdateApprovalPolicyRequest $request, CurrentTenant $currentTenant, SaveApprovalPolicyDraft $action, int $approvalPolicy): ApprovalPolicyResource
    {
        $policy = $this->findTenantPolicy($currentTenant, $approvalPolicy);

        return new ApprovalPolicyResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $request->validated(), $policy));
    }

    public function preview(
        PreviewApprovalPolicyRequest $request,
        CurrentTenant $currentTenant,
        PreviewApprovalPolicy $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $context = ApprovalContextData::fromArray((string) $tenant->id, $request->validated('context', []));
        $subjectType = $context->subjectType;

        $candidates = [[
            'matchedPolicy' => [
                'id' => 'preview',
                'tenantId' => (string) $tenant->id,
                'name' => $request->validated('policyName', 'Preview policy'),
                'subjectType' => $subjectType,
                'status' => 'draft',
            ],
            'matchedVersion' => [
                'id' => 'preview',
                'tenantId' => (string) $tenant->id,
                'policyId' => 'preview',
                'versionNumber' => 0,
                'status' => 'draft',
                'priority' => (int) $request->validated('priority', 100),
                'rules' => $request->validated('rules'),
                'routeTemplate' => $request->validated('routeTemplate'),
                'slaRules' => $request->validated('slaRules', []),
            ],
            'priority' => (int) $request->validated('priority', 100),
            'rules' => $request->validated('rules'),
            'routeTemplate' => $request->validated('routeTemplate'),
            'slaRules' => $request->validated('slaRules', []),
        ]];

        return (new ApprovalPreviewResource(
            $action->handle($tenant, $request->user(), $context, $candidates),
        ))->response();
    }

    private function findTenantPolicy(CurrentTenant $currentTenant, int $id): ApprovalPolicy
    {
        return ApprovalPolicy::query()
            ->where('tenant_id', $this->tenantOrAbort($currentTenant)->id)
            ->findOrFail($id);
    }

    private function authorizeAdmin(Request $request, CurrentTenant $currentTenant): void
    {
        abort_if($currentTenant->roleFor($request->user()) !== 'admin', 403);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
