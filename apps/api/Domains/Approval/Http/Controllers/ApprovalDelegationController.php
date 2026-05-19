<?php

namespace Domains\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\CancelApprovalDelegation;
use Domains\Approval\Actions\CreateApprovalDelegation;
use Domains\Approval\Http\Requests\StoreApprovalDelegationRequest;
use Domains\Approval\Http\Resources\ApprovalDelegationResource;
use Domains\Approval\Models\ApprovalDelegation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalDelegationController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $role = $currentTenant->roleFor($request->user());

        abort_unless(in_array($role, ['admin', 'approver'], true), 403);

        $query = ApprovalDelegation::query()
            ->with(['delegator', 'delegate', 'creator'])
            ->where('tenant_id', $tenant->id)
            ->latest('created_at');

        if ($role !== 'admin') {
            $query->where(function ($query) use ($request): void {
                $query->where('delegator_id', $request->user()->id)
                    ->orWhere('delegate_id', $request->user()->id);
            });
        }

        return response()->json([
            'data' => ApprovalDelegationResource::collection($query->get())->resolve(),
        ]);
    }

    public function candidates(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $role = $currentTenant->roleFor($request->user());

        abort_unless(in_array($role, ['admin', 'approver'], true), 403);

        $users = $tenant->users()
            ->whereKeyNot($request->user()->id)
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn ($user): array => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $users]);
    }

    public function store(StoreApprovalDelegationRequest $request, CurrentTenant $currentTenant, CreateApprovalDelegation $action): JsonResponse
    {
        $delegation = $action->handle($this->tenantOrAbort($currentTenant), $request->user(), $request->validated());

        return (new ApprovalDelegationResource($delegation))->response()->setStatusCode(201);
    }

    public function update(StoreApprovalDelegationRequest $request, CurrentTenant $currentTenant, CreateApprovalDelegation $action, ApprovalDelegation $approvalDelegation): ApprovalDelegationResource
    {
        $this->assertTenantDelegation($currentTenant, $approvalDelegation);

        return new ApprovalDelegationResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $request->validated(), $approvalDelegation));
    }

    public function cancel(Request $request, CurrentTenant $currentTenant, CancelApprovalDelegation $action, ApprovalDelegation $approvalDelegation): ApprovalDelegationResource
    {
        $this->assertTenantDelegation($currentTenant, $approvalDelegation);

        return new ApprovalDelegationResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $approvalDelegation));
    }

    private function assertTenantDelegation(CurrentTenant $currentTenant, ApprovalDelegation $approvalDelegation): void
    {
        abort_unless((int) $approvalDelegation->tenant_id === (int) $this->tenantOrAbort($currentTenant)->id, 404);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
