<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\CancelRfqDraft;
use Domains\Quotation\Actions\CreateOrRevealRfqDraftFromIntake;
use Domains\Quotation\Actions\UpdateRfqDraft;
use Domains\Quotation\Http\Requests\CancelRfqDraftRequest;
use Domains\Quotation\Http\Requests\UpdateRfqDraftRequest;
use Domains\Quotation\Http\Resources\RfqResource;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\SourcingIntakeReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    public function storeForIntake(Request $request, CurrentTenant $currentTenant, int $review, CreateOrRevealRfqDraftFromIntake $action): JsonResponse
    {
        $this->authorize('create', Rfq::class);
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantReview($tenant, $review);
        $result = $action->handle($tenant, $request->user(), $model);

        return (new RfqResource($result['rfq']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(CurrentTenant $currentTenant, int $rfq): RfqResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('view', $model);

        return new RfqResource($model);
    }

    public function update(UpdateRfqDraftRequest $request, CurrentTenant $currentTenant, int $rfq, UpdateRfqDraft $action): RfqResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('update', $model);

        return new RfqResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    public function cancel(CancelRfqDraftRequest $request, CurrentTenant $currentTenant, int $rfq, CancelRfqDraft $action): RfqResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('cancel', $model);

        return new RfqResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    private function findTenantReview(Tenant $tenant, int $id): SourcingIntakeReview
    {
        return SourcingIntakeReview::query()
            ->with(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->with(['sourcingIntakeReview.assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
