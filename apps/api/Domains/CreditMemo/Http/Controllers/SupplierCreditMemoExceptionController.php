<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\AcknowledgeSupplierCreditMemoException;
use Domains\CreditMemo\Actions\EscalateSupplierCreditMemoException;
use Domains\CreditMemo\Actions\ResolveSupplierCreditMemoException;
use Domains\CreditMemo\Http\Requests\AcknowledgeSupplierCreditMemoExceptionRequest;
use Domains\CreditMemo\Http\Requests\ResolveSupplierCreditMemoExceptionRequest;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoExceptionResource;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierCreditMemoExceptionController
{
    use AuthorizesRequests;

    public function index(
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('view', $creditMemo);

        $exceptions = SupplierCreditMemoException::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => SupplierCreditMemoExceptionResource::collection($exceptions),
        ]);
    }

    public function acknowledge(
        AcknowledgeSupplierCreditMemoExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoException $exception,
        AcknowledgeSupplierCreditMemoException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $exception = $this->findTenantException($tenant, $exception, $creditMemo);
        $this->authorize('acknowledge', $exception);

        $validated = $request->validated();

        $exception = $action->handle(
            $exception,
            $request->user(),
            (int) $validated['lockVersion'],
        );

        return response()->json([
            'data' => (new SupplierCreditMemoExceptionResource($exception))->resolve($request),
        ]);
    }

    public function resolve(
        ResolveSupplierCreditMemoExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoException $exception,
        ResolveSupplierCreditMemoException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $exception = $this->findTenantException($tenant, $exception, $creditMemo);
        $this->authorize('resolve', $exception);

        $validated = $request->validated();

        $exception = $action->handle(
            $exception,
            $request->user(),
            (int) $validated['lockVersion'],
            $validated['resolutionType'],
            (string) $validated['resolutionNotes'],
        );

        return response()->json([
            'data' => (new SupplierCreditMemoExceptionResource($exception))->resolve($request),
        ]);
    }

    public function escalate(
        AcknowledgeSupplierCreditMemoExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoException $exception,
        EscalateSupplierCreditMemoException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $exception = $this->findTenantException($tenant, $exception, $creditMemo);
        $this->authorize('escalate', $exception);

        $validated = $request->validated();

        $exception = $action->handle(
            $exception,
            $request->user(),
            (int) $validated['lockVersion'],
        );

        return response()->json([
            'data' => (new SupplierCreditMemoExceptionResource($exception))->resolve($request),
        ]);
    }

    private function findTenantCreditMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        return SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($creditMemo->id);
    }

    private function findTenantException(
        Tenant $tenant,
        SupplierCreditMemoException $exception,
        SupplierCreditMemo $creditMemo,
    ): SupplierCreditMemoException {
        return SupplierCreditMemoException::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->findOrFail($exception->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
