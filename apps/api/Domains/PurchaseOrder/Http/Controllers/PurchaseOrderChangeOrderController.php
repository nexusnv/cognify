<?php

namespace Domains\PurchaseOrder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Actions\CancelPurchaseOrderChangeOrder;
use Domains\PurchaseOrder\Actions\CreateOrUpdatePurchaseOrderChangeOrder;
use Domains\PurchaseOrder\Actions\SubmitPurchaseOrderChangeOrder;
use Domains\PurchaseOrder\Http\Requests\CancelPurchaseOrderChangeOrderRequest;
use Domains\PurchaseOrder\Http\Requests\SavePurchaseOrderChangeOrderRequest;
use Domains\PurchaseOrder\Http\Requests\SubmitPurchaseOrderChangeOrderRequest;
use Domains\PurchaseOrder\Http\Resources\PurchaseOrderChangeOrderResource;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchaseOrderChangeOrderController extends Controller
{
    public function index(
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('viewChangeOrder', $purchaseOrder);

        $changeOrders = $purchaseOrder->changeOrders()->with(['purchaseOrder.lines', 'purchaseOrder.currentChangeOrder', 'lines'])->get();

        return response()->json([
            'data' => PurchaseOrderChangeOrderResource::collection($changeOrders)->resolve(request()),
        ]);
    }

    public function show(
        CurrentTenant $currentTenant,
        PurchaseOrderChangeOrder $changeOrder,
    ): JsonResponse {
        $changeOrder = $this->findTenantChangeOrder($this->tenantOrAbort($currentTenant), $changeOrder);
        $this->authorize('viewChangeOrder', $changeOrder->purchaseOrder);

        return response()->json([
            'data' => (new PurchaseOrderChangeOrderResource($changeOrder->load(['purchaseOrder.lines', 'purchaseOrder.currentChangeOrder', 'purchaseOrder.changeOrders', 'lines'])))->resolve(request()),
        ]);
    }

    public function store(
        SavePurchaseOrderChangeOrderRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        CreateOrUpdatePurchaseOrderChangeOrder $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('saveChangeOrder', $purchaseOrder);

        $changeOrder = $action->handle($purchaseOrder, $request->user(), $request->validated());

        return response()->json([
            'data' => (new PurchaseOrderChangeOrderResource($changeOrder->load(['purchaseOrder.lines', 'purchaseOrder.currentChangeOrder', 'purchaseOrder.changeOrders', 'lines'])))->resolve($request),
        ], 201);
    }

    public function submit(
        SubmitPurchaseOrderChangeOrderRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrderChangeOrder $changeOrder,
        SubmitPurchaseOrderChangeOrder $action,
    ): JsonResponse {
        $changeOrder = $this->findTenantChangeOrder($this->tenantOrAbort($currentTenant), $changeOrder);
        $this->authorize('submitChangeOrder', $changeOrder->purchaseOrder);

        $result = $action->handle($changeOrder, $request->user(), (int) $request->validated('lockVersion'));

        return response()->json([
            'data' => (new PurchaseOrderChangeOrderResource($result->load(['purchaseOrder.lines', 'purchaseOrder.currentChangeOrder', 'purchaseOrder.changeOrders', 'lines'])))->resolve($request),
        ]);
    }

    public function cancel(
        CancelPurchaseOrderChangeOrderRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrderChangeOrder $changeOrder,
        CancelPurchaseOrderChangeOrder $action,
    ): JsonResponse {
        $changeOrder = $this->findTenantChangeOrder($this->tenantOrAbort($currentTenant), $changeOrder);
        $this->authorize('cancelChangeOrder', $changeOrder->purchaseOrder);

        $result = $action->handle($changeOrder, $request->user(), (int) $request->validated('lockVersion'), (string) $request->validated('reason'));

        return response()->json([
            'data' => (new PurchaseOrderChangeOrderResource($result->load(['purchaseOrder.lines', 'purchaseOrder.currentChangeOrder', 'purchaseOrder.changeOrders', 'lines'])))->resolve($request),
        ]);
    }

    private function findTenantPurchaseOrder(Tenant $tenant, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($purchaseOrder->id)
            ->firstOrFail();
    }

    private function findTenantChangeOrder(Tenant $tenant, PurchaseOrderChangeOrder $changeOrder): PurchaseOrderChangeOrder
    {
        return PurchaseOrderChangeOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($changeOrder->id)
            ->firstOrFail();
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
