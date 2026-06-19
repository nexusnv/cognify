<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;

class RetrySupplierInvoicePayment
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

            if ($invoice->payment_status !== null && $invoice->payment_status->isTerminal()) {
                return;
            }

            if ($invoice->statusState() !== SupplierInvoiceStatus::Approved) {
                return;
            }

            if ($invoice->payment_status === SupplierInvoicePaymentStatus::PaymentEligible) {
                return;
            }

            $invoice->loadMissing('exceptions');
            $hasUnresolvedExceptions = $invoice->exceptions->contains(fn ($e) => $e->resolved_at === null);

            $before = $invoice->only([
                'payment_status', 'payment_eligible_at', 'payment_on_hold_by_user_id',
                'payment_on_hold_at', 'payment_on_hold_reason', 'payment_hold_released_by_user_id',
                'payment_hold_released_at', 'payment_hold_released_note', 'lock_version',
            ]);

            if ($hasUnresolvedExceptions) {
                return;
            }

            $invoice->forceFill([
                'payment_status' => SupplierInvoicePaymentStatus::PaymentEligible,
                'payment_eligible_at' => now(),
                'payment_on_hold_by_user_id' => null,
                'payment_on_hold_at' => null,
                'payment_on_hold_reason' => null,
                'payment_hold_released_by_user_id' => null,
                'payment_hold_released_at' => null,
                'payment_hold_released_note' => null,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.payment_retry',
                subject: $invoice,
                metadata: ['invoiceId' => (string) $invoice->id],
                before: $before,
                after: $invoice->only([
                    'payment_status', 'payment_eligible_at', 'payment_on_hold_by_user_id',
                    'payment_on_hold_at', 'payment_on_hold_reason', 'payment_hold_released_by_user_id',
                    'payment_hold_released_at', 'payment_hold_released_note', 'lock_version',
                ]),
            ));
        });
    }
}
