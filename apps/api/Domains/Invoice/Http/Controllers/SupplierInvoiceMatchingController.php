<?php

namespace Domains\Invoice\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Invoice\Actions\RunInvoiceMatching;
use Domains\Invoice\Http\Requests\RunInvoiceMatchingRequest;
use Domains\Invoice\Http\Resources\SupplierInvoiceMatchResultResource;
use Domains\Invoice\Http\Resources\SupplierInvoiceResource;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierInvoiceMatchingController
{
    use AuthorizesRequests;

    public function run(
        RunInvoiceMatchingRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        RunInvoiceMatching $action,
    ): JsonResponse {
        $supplierInvoice = $this->findTenantSupplierInvoice($this->tenantOrAbort($currentTenant), $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        $invoice = $action->handle(
            $supplierInvoice,
            $request->user(),
            (int) $request->validated('lockVersion'),
            'manual',
        );

        $invoice->load(['lines', 'purchaseOrder', 'vendor', 'matchResults']);

        return response()->json(
            (new SupplierInvoiceResource($invoice))->resolve($request),
        );
    }

    public function results(
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse {
        $supplierInvoice = $this->findTenantSupplierInvoice($this->tenantOrAbort($currentTenant), $supplierInvoice);
        $this->authorize('view', $supplierInvoice);

        $results = SupplierInvoiceMatchResult::query()
            ->where('tenant_id', $supplierInvoice->tenant_id)
            ->where('supplier_invoice_id', $supplierInvoice->id)
            ->with('supplierInvoiceLine')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => SupplierInvoiceMatchResultResource::collection($results),
        ]);
    }

    private function findTenantSupplierInvoice(Tenant $tenant, SupplierInvoice $supplierInvoice): SupplierInvoice
    {
        return SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->with(['lines', 'purchaseOrder', 'vendor'])
            ->findOrFail($supplierInvoice->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
