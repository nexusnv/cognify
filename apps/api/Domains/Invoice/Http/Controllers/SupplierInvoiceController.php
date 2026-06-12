<?php

namespace Domains\Invoice\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Invoice\Actions\CaptureSupplierInvoice;
use Domains\Invoice\Http\Requests\CaptureSupplierInvoiceRequest;
use Domains\Invoice\Http\Resources\SupplierInvoiceResource;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SupplierInvoiceController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CaptureSupplierInvoice $captureSupplierInvoice,
    ) {}

    public function index(CurrentTenant $currentTenant, PurchaseOrder $purchaseOrder): ResourceCollection
    {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('view', $purchaseOrder);

        $invoices = SupplierInvoice::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->with('lines')
            ->orderByDesc('invoice_date')
            ->orderByDesc('created_at')
            ->get();

        return SupplierInvoiceResource::collection($invoices);
    }

    public function store(
        CaptureSupplierInvoiceRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('captureInvoice', $purchaseOrder);

        try {
            $invoice = $this->captureSupplierInvoice->handle(
                purchaseOrder: $purchaseOrder,
                actor: $request->user(),
                payload: $request->validated(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'lines' => [$e->getMessage()],
            ]);
        }

        return (new SupplierInvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function show(CurrentTenant $currentTenant, SupplierInvoice $supplierInvoice, Request $request): JsonResponse
    {
        $supplierInvoice = $this->findTenantSupplierInvoice($this->tenantOrAbort($currentTenant), $supplierInvoice);
        $this->authorize('view', $supplierInvoice);

        $supplierInvoice->load('lines');

        return response()->json([
            'data' => (new SupplierInvoiceResource($supplierInvoice))->resolve($request),
        ]);
    }

    private function findTenantPurchaseOrder(Tenant $tenant, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $tenantPurchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($purchaseOrder->id)
            ->first();

        if ($tenantPurchaseOrder === null) {
            abort(403, 'You are not allowed to access this purchase order.');
        }

        return $tenantPurchaseOrder;
    }

    private function findTenantSupplierInvoice(Tenant $tenant, SupplierInvoice $supplierInvoice): SupplierInvoice
    {
        $tenantSupplierInvoice = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($supplierInvoice->id)
            ->with('lines')
            ->first();

        if ($tenantSupplierInvoice === null) {
            abort(403, 'You are not allowed to access this supplier invoice.');
        }

        return $tenantSupplierInvoice;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
