<?php

namespace Domains\AccountsPayable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Actions\HoldSupplierInvoicePayment;
use Domains\AccountsPayable\Actions\ReleaseSupplierInvoicePaymentHold;
use Domains\AccountsPayable\Actions\RetrySupplierInvoicePayment;
use Domains\AccountsPayable\Http\Requests\PlaceInvoiceOnPaymentHoldRequest;
use Domains\AccountsPayable\Http\Requests\ReleaseInvoiceHoldRequest;
use Domains\AccountsPayable\Http\Requests\RetryPaymentInductionRequest;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;

class SupplierInvoicePaymentController extends Controller
{
    public function placeHold(
        PlaceInvoiceOnPaymentHoldRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        HoldSupplierInvoicePayment $action,
    ): JsonResponse {
        $invoice = $this->findTenantInvoice($currentTenant, $supplierInvoice);
        $this->authorize('placeHold', $invoice);

        $updatedInvoice = $action->handle(
            $invoice,
            $request->user(),
            (int) $request->validated('lockVersion'),
            $request->validated('reason'),
        );

        return $this->paymentResponse($updatedInvoice);
    }

    public function releaseHold(
        ReleaseInvoiceHoldRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        ReleaseSupplierInvoicePaymentHold $action,
    ): JsonResponse {
        $invoice = $this->findTenantInvoice($currentTenant, $supplierInvoice);
        $this->authorize('releaseHold', $invoice);

        $updatedInvoice = $action->handle(
            $invoice,
            $request->user(),
            (int) $request->validated('lockVersion'),
            $request->validated('releaseNote'),
        );

        return $this->paymentResponse($updatedInvoice);
    }

    public function retryInduction(
        RetryPaymentInductionRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        RetrySupplierInvoicePayment $action,
    ): JsonResponse {
        $invoice = $this->findTenantInvoice($currentTenant, $supplierInvoice);
        $this->authorize('retryPaymentInduction', $invoice);

        $action->handle($invoice, $request->user());

        return $this->paymentResponse($invoice->fresh());
    }

    private function findTenantInvoice(CurrentTenant $currentTenant, SupplierInvoice $supplierInvoice): SupplierInvoice
    {
        $tenant = $this->tenantOrAbort($currentTenant);

        $tenantInvoice = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($supplierInvoice->id)
            ->first();

        if ($tenantInvoice === null) {
            abort(403, 'You are not allowed to access this supplier invoice.');
        }

        return $tenantInvoice;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }

    private function paymentResponse(SupplierInvoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => (string) $invoice->id,
                'paymentStatus' => $invoice->payment_status?->value,
                'paymentStatusLabel' => $invoice->payment_status?->label(),
                'lockVersion' => $invoice->lock_version,
            ],
        ]);
    }
}
