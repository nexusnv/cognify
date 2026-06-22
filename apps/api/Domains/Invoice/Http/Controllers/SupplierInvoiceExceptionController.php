<?php

namespace Domains\Invoice\Http\Controllers;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Invoice\Actions\EscalateInvoiceException;
use Domains\Invoice\Actions\ResolveInvoiceException;
use Domains\Invoice\Http\Requests\EscalateInvoiceExceptionRequest;
use Domains\Invoice\Http\Requests\ResolveInvoiceExceptionRequest;
use Domains\Invoice\Http\Resources\SupplierInvoiceExceptionResource;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceExceptionController
{
    use AuthorizesRequests;

    public function index(
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $supplierInvoice = $this->findTenantSupplierInvoice($tenant, $supplierInvoice);
        $this->authorize('view', $supplierInvoice);

        $exceptions = SupplierInvoiceException::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_invoice_id', $supplierInvoice->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => SupplierInvoiceExceptionResource::collection($exceptions),
        ]);
    }

    public function resolve(
        ResolveInvoiceExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        ResolveInvoiceException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $supplierInvoice = $this->findTenantSupplierInvoice($tenant, $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        $exception = $action->handle(
            $supplierInvoice,
            $exception,
            $request->user(),
            $request->validated('resolutionType'),
            $request->validated('adjustedValue'),
            $request->validated('explanation'),
            (int) $request->validated('lockVersion'),
        );

        return response()->json([
            'data' => (new SupplierInvoiceExceptionResource($exception))->resolve($request),
        ]);
    }

    public function escalate(
        EscalateInvoiceExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        EscalateInvoiceException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $supplierInvoice = $this->findTenantSupplierInvoice($tenant, $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        $escalatedToUser = User::query()
            ->whereKey($request->validated('escalatedToUserId'))
            ->whereHas('tenants', fn ($q) => $q->whereKey($tenant->id))
            ->first();

        if ($escalatedToUser === null) {
            throw ValidationException::withMessages([
                'escalatedToUserId' => ['The selected user is not a member of this tenant.'],
            ]);
        }

        $exception = $action->handle(
            $supplierInvoice,
            $exception,
            $request->user(),
            $escalatedToUser,
            $request->validated('note'),
            (int) $request->validated('lockVersion'),
        );

        return response()->json([
            'data' => (new SupplierInvoiceExceptionResource($exception))->resolve($request),
        ]);
    }

    private function findTenantSupplierInvoice(Tenant $tenant, SupplierInvoice $supplierInvoice): SupplierInvoice
    {
        return SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($supplierInvoice->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
