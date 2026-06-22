<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\AddSupplierCreditMemoLine;
use Domains\CreditMemo\Actions\RemoveSupplierCreditMemoLine;
use Domains\CreditMemo\Actions\UpdateSupplierCreditMemoLine;
use Domains\CreditMemo\Http\Requests\AddSupplierCreditMemoLineRequest;
use Domains\CreditMemo\Http\Requests\UpdateSupplierCreditMemoLineRequest;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoLineResource;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoResource;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierCreditMemoLineController
{
    use AuthorizesRequests;

    public function store(
        AddSupplierCreditMemoLineRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        AddSupplierCreditMemoLine $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('update', $creditMemo);

        $validated = $request->validated();

        $line = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $validated['lockVersion'],
            (int) $validated['lineNumber'],
            $validated['description'],
            $validated['quantity'],
            $validated['unitPrice'],
            $validated['taxCode'] ?? null,
            $validated['taxAmount'],
            $validated['purchaseOrderLineId'] ?? null,
            $validated['originalInvoiceLineId'] ?? null,
            $validated['notes'] ?? null,
        );

        $creditMemo->fresh(['lines', 'exceptions']);

        return response()->json([
            'data' => (new SupplierCreditMemoLineResource($line))->resolve($request),
            'creditMemo' => (new SupplierCreditMemoResource($creditMemo))->resolve($request),
        ], 201);
    }

    public function update(
        UpdateSupplierCreditMemoLineRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoLine $line,
        UpdateSupplierCreditMemoLine $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('update', $creditMemo);

        $line = SupplierCreditMemoLine::query()
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->findOrFail($line->id);

        $validated = $request->validated();

        $line = $action->handle(
            $line,
            $request->user(),
            (int) $validated['lockVersion'],
            $validated['description'] ?? null,
            $validated['quantity'] ?? null,
            $validated['unitPrice'] ?? null,
            $validated['taxCode'] ?? null,
            $validated['taxAmount'] ?? null,
            $validated['notes'] ?? null,
        );

        $creditMemo->fresh(['lines', 'exceptions']);

        return response()->json([
            'data' => (new SupplierCreditMemoLineResource($line))->resolve($request),
            'creditMemo' => (new SupplierCreditMemoResource($creditMemo))->resolve($request),
        ]);
    }

    public function destroy(
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoLine $line,
        Request $request,
        RemoveSupplierCreditMemoLine $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('update', $creditMemo);

        $line = SupplierCreditMemoLine::query()
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->findOrFail($line->id);

        $lockVersion = (int) $request->input('lockVersion', $line->lock_version);

        $action->handle($line, $request->user(), $lockVersion);

        $creditMemo->fresh(['lines', 'exceptions']);

        return response()->json([
            'creditMemo' => (new SupplierCreditMemoResource($creditMemo))->resolve($request),
        ]);
    }

    private function findTenantCreditMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        return SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($creditMemo->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
