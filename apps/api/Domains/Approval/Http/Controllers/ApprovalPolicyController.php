<?php

namespace Domains\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\SaveApprovalPolicyDraft;
use Domains\Approval\Http\Requests\StoreApprovalPolicyRequest;
use Domains\Approval\Http\Requests\UpdateApprovalPolicyRequest;
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
