<?php

namespace Domains\Invoice\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Invoice\Actions\CompleteSupplierInvoiceReview;
use Domains\Invoice\Actions\MarkSupplierInvoiceNeedsInformation;
use Domains\Invoice\Actions\StartSupplierInvoiceReview;
use Domains\Invoice\Http\Requests\CompleteSupplierInvoiceReviewRequest;
use Domains\Invoice\Http\Requests\MarkSupplierInvoiceNeedsInformationRequest;
use Domains\Invoice\Http\Requests\StartSupplierInvoiceReviewRequest;
use Domains\Invoice\Http\Resources\SupplierInvoiceResource;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SupplierInvoiceReviewController
{
    use AuthorizesRequests;

    public function start(
        StartSupplierInvoiceReviewRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        StartSupplierInvoiceReview $action,
    ): JsonResponse {
        $supplierInvoice = $this->findTenantSupplierInvoice($this->tenantOrAbort($currentTenant), $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        $invoice = $action->handle($supplierInvoice, $request->user(), (int) $request->validated('lockVersion'));

        return $this->response($invoice, $request);
    }

    public function needsInformation(
        MarkSupplierInvoiceNeedsInformationRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        MarkSupplierInvoiceNeedsInformation $action,
    ): JsonResponse {
        $supplierInvoice = $this->findTenantSupplierInvoice($this->tenantOrAbort($currentTenant), $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        try {
            $validated = $request->validated();
            $invoice = $action->handle(
                $supplierInvoice,
                $request->user(),
                (int) $validated['lockVersion'],
                (string) $validated['notes'],
                (array) $validated['checklist'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['checklist' => [$exception->getMessage()]]);
        }

        return $this->response($invoice, $request);
    }

    public function complete(
        CompleteSupplierInvoiceReviewRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        CompleteSupplierInvoiceReview $action,
    ): JsonResponse {
        $supplierInvoice = $this->findTenantSupplierInvoice($this->tenantOrAbort($currentTenant), $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        try {
            $validated = $request->validated();
            $invoice = $action->handle(
                $supplierInvoice,
                $request->user(),
                (int) $validated['lockVersion'],
                isset($validated['notes']) && is_string($validated['notes']) ? $validated['notes'] : null,
                (array) $validated['checklist'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['checklist' => [$exception->getMessage()]]);
        }

        return $this->response($invoice, $request);
    }

    private function response(SupplierInvoice $invoice, Request $request): JsonResponse
    {
        $invoice->loadMissing(['lines', 'purchaseOrder', 'vendor']);
        $invoice->loadCount('attachments');

        return response()->json([
            'data' => (new SupplierInvoiceResource($invoice))->resolve($request),
        ]);
    }

    private function findTenantSupplierInvoice(Tenant $tenant, SupplierInvoice $supplierInvoice): SupplierInvoice
    {
        $tenantSupplierInvoice = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($supplierInvoice->id)
            ->with(['lines', 'purchaseOrder', 'vendor'])
            ->withCount('attachments')
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
