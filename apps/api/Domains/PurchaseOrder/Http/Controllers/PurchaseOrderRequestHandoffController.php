<?php

namespace Domains\PurchaseOrder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Actions\CancelPurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\Actions\CreateOrRevealPurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\Actions\ExportPurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\Actions\MarkPurchaseOrderRequestHandoffReady;
use Domains\PurchaseOrder\Actions\UpdatePurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\Http\Requests\CancelPurchaseOrderRequestHandoffRequest;
use Domains\PurchaseOrder\Http\Requests\MarkPurchaseOrderRequestHandoffReadyRequest;
use Domains\PurchaseOrder\Http\Requests\UpdatePurchaseOrderRequestHandoffRequest;
use Domains\PurchaseOrder\Http\Resources\PurchaseOrderRequestHandoffResource;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchaseOrderRequestHandoffController extends Controller
{
    public function showForRfq(
        CurrentTenant $currentTenant,
        int $rfq,
        CreateOrRevealPurchaseOrderRequestHandoff $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $recommendation = $this->findTenantRecommendationForRfq($tenant, $model);

        $this->authorize('create', [PurchaseOrderRequestHandoff::class, $tenant]);

        $handoff = $this->findTenantHandoffForRecommendation($tenant, $recommendation);

        if ($handoff === null) {
            if ($recommendation->statusState() !== RfqAwardRecommendationStatus::Approved) {
                throw new ConflictHttpException('Only approved award recommendations can create PO handoffs.');
            }

            $handoff = $action->handle($recommendation, request()->user());
        }

        $this->authorize('view', $handoff);

        return $this->resourceResponse($handoff);
    }

    public function createForRfq(
        CurrentTenant $currentTenant,
        int $rfq,
        CreateOrRevealPurchaseOrderRequestHandoff $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $recommendation = $this->findTenantRecommendationForRfq($tenant, $model);

        $this->authorize('create', [PurchaseOrderRequestHandoff::class, $tenant]);

        return $this->resourceResponse($action->handle($recommendation, request()->user()));
    }

    public function show(CurrentTenant $currentTenant, PurchaseOrderRequestHandoff $handoff): JsonResponse
    {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('view', $handoff);

        return $this->resourceResponse($handoff);
    }

    public function update(
        UpdatePurchaseOrderRequestHandoffRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrderRequestHandoff $handoff,
        UpdatePurchaseOrderRequestHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('update', $handoff);

        return $this->resourceResponse($action->handle($handoff, $request->user(), $request->validated()));
    }

    public function ready(
        MarkPurchaseOrderRequestHandoffReadyRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrderRequestHandoff $handoff,
        MarkPurchaseOrderRequestHandoffReady $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('markReady', $handoff);

        return $this->resourceResponse($action->handle($handoff, $request->user(), (int) $request->validated('lockVersion')));
    }

    public function cancel(
        CancelPurchaseOrderRequestHandoffRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrderRequestHandoff $handoff,
        CancelPurchaseOrderRequestHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('cancel', $handoff);

        return $this->resourceResponse($action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            (string) $request->validated('reason'),
        ));
    }

    public function exportJson(
        CurrentTenant $currentTenant,
        PurchaseOrderRequestHandoff $handoff,
        ExportPurchaseOrderRequestHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('export', $handoff);

        return response()->json($action->handle($handoff, request()->user(), 'json'));
    }

    public function exportCsv(
        CurrentTenant $currentTenant,
        PurchaseOrderRequestHandoff $handoff,
        ExportPurchaseOrderRequestHandoff $action,
    ): Response {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('export', $handoff);

        $csv = $action->handle($handoff, request()->user(), 'csv');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$handoff->number.'.csv"',
        ]);
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantRecommendationForRfq(Tenant $tenant, Rfq $rfq): RfqAwardRecommendation
    {
        return RfqAwardRecommendation::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->latest('updated_at')
            ->latest('id')
            ->firstOrFail();
    }

    private function findTenantHandoffForRecommendation(Tenant $tenant, RfqAwardRecommendation $recommendation): ?PurchaseOrderRequestHandoff
    {
        return PurchaseOrderRequestHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_award_recommendation_id', $recommendation->id)
            ->first();
    }

    private function findTenantHandoff(Tenant $tenant, PurchaseOrderRequestHandoff $handoff): PurchaseOrderRequestHandoff
    {
        $tenantHandoff = PurchaseOrderRequestHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($handoff->id)
            ->first();

        if ($tenantHandoff === null) {
            abort(403, 'You are not allowed to access this PO handoff.');
        }

        return $tenantHandoff;
    }

    private function resourceResponse(PurchaseOrderRequestHandoff $handoff): JsonResponse
    {
        return response()->json([
            'data' => (new PurchaseOrderRequestHandoffResource($handoff))->resolve(),
        ]);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
