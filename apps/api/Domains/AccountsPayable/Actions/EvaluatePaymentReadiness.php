<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;

class EvaluatePaymentReadiness
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(SupplierInvoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor) {
            $invoice = SupplierInvoice::query()
                ->whereKey($invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::Approved) {
                return;
            }

            if ($invoice->payment_status === SupplierInvoicePaymentStatus::PaymentEligible) {
                return;
            }

            if ($invoice->payment_status === SupplierInvoicePaymentStatus::OnHold) {
                return;
            }

            if ($invoice->payment_status === SupplierInvoicePaymentStatus::HandoffExported) {
                return;
            }

            $invoice->loadMissing('exceptions');
            $hasUnresolvedExceptions = $invoice->exceptions->contains(fn ($e) => $e->resolved_at === null);

            $before = ['payment_status' => $invoice->payment_status?->value];

            if ($hasUnresolvedExceptions) {
                return;
            }

            $invoice->forceFill([
                'payment_status' => SupplierInvoicePaymentStatus::PaymentEligible,
                'payment_eligible_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.payment_readiness_evaluated',
                subject: $invoice,
                metadata: ['invoiceId' => (string) $invoice->id, 'paymentStatus' => 'payment_eligible'],
                before: $before,
                after: ['payment_status' => 'payment_eligible', 'payment_eligible_at' => now()->toISOString()],
            ));
        });
    }
}
