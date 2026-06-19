<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;

class AutoAdvanceToPaymentEligible
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(SupplierInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = SupplierInvoice::query()
                ->whereKey($invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->payment_status !== null) {
                return;
            }

            if ($invoice->statusState() !== SupplierInvoiceStatus::Approved) {
                return;
            }

            $before = ['payment_status' => $invoice->payment_status?->value];

            $invoice->forceFill([
                'payment_status' => SupplierInvoicePaymentStatus::PaymentEligible,
                'payment_eligible_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: null,
                action: 'supplier_invoice.payment_eligible',
                subject: $invoice,
                metadata: ['invoiceId' => (string) $invoice->id, 'paymentStatus' => 'payment_eligible'],
                before: $before,
                after: ['payment_status' => 'payment_eligible', 'payment_eligible_at' => now()->toISOString()],
            ));
        });
    }
}
