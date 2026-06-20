<?php

namespace Domains\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Actions\AddApPaymentAllocation;
use Domains\Payments\Http\Requests\AddApPaymentAllocationRequest;
use Domains\Payments\Http\Resources\ApPaymentAllocationResource;
use Domains\Payments\Models\ApPaymentAllocation;
use Illuminate\Http\JsonResponse;

class ApPaymentAllocationController extends Controller
{
    public function index(CurrentTenant $currentTenant, ApPaymentHandoff $handoff): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $handoff = $this->findTenantHandoff($tenant, $handoff);
        $this->authorize('view', ApPaymentAllocation::class);

        $allocations = ApPaymentAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('ap_payment_handoff_id', $handoff->id)
            ->with('supplierInvoice')
            ->orderBy('allocation_date')
            ->get();

        return response()->json([
            'data' => ApPaymentAllocationResource::collection($allocations)->resolve(),
        ]);
    }

    public function store(
        AddApPaymentAllocationRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        AddApPaymentAllocation $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $handoff = $this->findTenantHandoff($tenant, $handoff);
        $this->authorize('create', ApPaymentAllocation::class);

        $invoice = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($request->validated('supplierInvoiceId'))
            ->firstOrFail();

        $allocation = $action->handle(
            $handoff,
            $invoice,
            $request->user(),
            (int) $request->validated('lockVersion'),
            allocatedAmount: (string) $request->validated('allocatedAmount'),
            allocationDate: (string) $request->validated('allocationDate'),
            paymentReference: $request->validated('paymentReference'),
            settlementAmount: $request->validated('settlementAmount'),
            settlementCurrency: $request->validated('settlementCurrency'),
        );

        return response()->json([
            'data' => (new ApPaymentAllocationResource($allocation->fresh()))->resolve(),
        ], 201);
    }

    public function show(CurrentTenant $currentTenant, ApPaymentAllocation $allocation): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantAllocation = ApPaymentAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($allocation->id)
            ->first();
        if ($tenantAllocation === null) {
            abort(403, 'You are not allowed to access this allocation.');
        }
        $this->authorize('view', $tenantAllocation);

        return response()->json([
            'data' => (new ApPaymentAllocationResource($tenantAllocation))->resolve(),
        ]);
    }

    private function findTenantHandoff(Tenant $tenant, ApPaymentHandoff $handoff): ApPaymentHandoff
    {
        $tenantHandoff = ApPaymentHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($handoff->id)
            ->first();
        if ($tenantHandoff === null) {
            abort(403, 'You are not allowed to access this AP payment handoff.');
        }
        return $tenantHandoff;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
