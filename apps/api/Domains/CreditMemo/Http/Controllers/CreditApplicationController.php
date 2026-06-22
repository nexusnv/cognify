<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\CreateCreditApplication;
use Domains\CreditMemo\Actions\VoidCreditApplication;
use Domains\CreditMemo\Http\Requests\CreateCreditApplicationRequest;
use Domains\CreditMemo\Http\Requests\VoidCreditApplicationRequest;
use Domains\CreditMemo\Http\Resources\CreditApplicationResource;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class CreditApplicationController
{
    use AuthorizesRequests;

    public function index(
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('view', $creditMemo);

        $applications = CreditApplication::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->with(['invoice', 'creditMemo'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => CreditApplicationResource::collection($applications),
        ]);
    }

    public function store(
        CreateCreditApplicationRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        CreateCreditApplication $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('apply', $creditMemo);

        $validated = $request->validated();

        $invoice = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['supplierInvoiceId']);

        $application = $action->handle(
            $creditMemo,
            $invoice,
            $request->user(),
            (int) $validated['lockVersion'],
            $validated['appliedAmount'],
            $validated['applicationDate'],
            $validated['notes'] ?? null,
        );

        return (new CreditApplicationResource($application))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        CurrentTenant $currentTenant,
        CreditApplication $application,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $application = $this->findTenantApplication($tenant, $application);
        $this->authorize('view', $application);

        $application->load(['invoice', 'creditMemo']);

        return response()->json([
            'data' => (new CreditApplicationResource($application))->resolve(request()),
        ]);
    }

    public function destroy(
        VoidCreditApplicationRequest $request,
        CurrentTenant $currentTenant,
        CreditApplication $application,
        VoidCreditApplication $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $application = $this->findTenantApplication($tenant, $application);
        $this->authorize('void', $application);

        $validated = $request->validated();

        $application = $action->handle(
            $application,
            $request->user(),
            (int) $validated['lockVersion'],
            $validated['voidReason'],
        );

        return response()->json([
            'data' => (new CreditApplicationResource($application))->resolve($request),
        ]);
    }

    private function findTenantCreditMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        return SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($creditMemo->id);
    }

    private function findTenantApplication(Tenant $tenant, CreditApplication $application): CreditApplication
    {
        return CreditApplication::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($application->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
