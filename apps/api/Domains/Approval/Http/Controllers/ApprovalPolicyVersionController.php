<?php

namespace Domains\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\CreateApprovalPolicyVersionDraft;
use Domains\Approval\Actions\PublishApprovalPolicyVersion;
use Domains\Approval\Http\Requests\StoreApprovalPolicyVersionRequest;
use Domains\Approval\Http\Resources\ApprovalPolicyVersionResource;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalPolicyVersionController extends Controller
{
    public function store(StoreApprovalPolicyVersionRequest $request, CurrentTenant $currentTenant, CreateApprovalPolicyVersionDraft $action, int $approvalPolicy): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $policy = $this->findTenantPolicy($tenant, $approvalPolicy);

        return (new ApprovalPolicyVersionResource(
            $action->handle($tenant, $request->user(), $policy, $request->validated()),
        ))->response()->setStatusCode(201);
    }

    public function publish(Request $request, CurrentTenant $currentTenant, PublishApprovalPolicyVersion $action, int $approvalPolicyVersion): ApprovalPolicyVersionResource
    {
        $this->authorizeAdmin($request, $currentTenant);
        $version = $this->findTenantVersion($currentTenant, $approvalPolicyVersion);

        return new ApprovalPolicyVersionResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $version));
    }

    public function retire(Request $request, CurrentTenant $currentTenant, PublishApprovalPolicyVersion $action, int $approvalPolicyVersion): ApprovalPolicyVersionResource
    {
        $this->authorizeAdmin($request, $currentTenant);
        $version = $this->findTenantVersion($currentTenant, $approvalPolicyVersion);

        return new ApprovalPolicyVersionResource($action->retire($this->tenantOrAbort($currentTenant), $request->user(), $version));
    }

    private function findTenantPolicy(Tenant $tenant, int $id): ApprovalPolicy
    {
        return ApprovalPolicy::query()->where('tenant_id', $tenant->id)->findOrFail($id);
    }

    private function findTenantVersion(CurrentTenant $currentTenant, int $id): ApprovalPolicyVersion
    {
        return ApprovalPolicyVersion::query()
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
