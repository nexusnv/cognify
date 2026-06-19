<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReleaseSupplierInvoicePaymentHold
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(
        SupplierInvoice $invoice,
        User $actor,
        int $lockVersion,
        ?string $note = null,
    ): SupplierInvoice {
        return DB::transaction(function () use ($invoice, $actor, $lockVersion, $note) {
            $invoice = SupplierInvoice::query()
                ->whereKey($invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->payment_status !== SupplierInvoicePaymentStatus::OnHold) {
                throw new ConflictHttpException('Only on-hold invoices can be released from payment hold.');
            }

            $invoice->assertLockVersion($lockVersion);

            $before = $invoice->only([
                'payment_status', 'payment_on_hold_by_user_id', 'payment_on_hold_at',
                'payment_on_hold_reason', 'payment_hold_released_by_user_id',
                'payment_hold_released_at', 'payment_hold_released_note', 'lock_version',
            ]);

            $invoice->forceFill([
                'payment_status' => SupplierInvoicePaymentStatus::PaymentEligible,
                'payment_hold_released_by_user_id' => $actor->id,
                'payment_hold_released_at' => now(),
                'payment_hold_released_note' => $note,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.payment_hold_released',
                subject: $invoice,
                metadata: ['invoiceId' => (string) $invoice->id, 'note' => $note],
                before: $before,
                after: $invoice->only([
                    'payment_status', 'payment_hold_released_by_user_id',
                    'payment_hold_released_at', 'payment_hold_released_note', 'lock_version',
                ]),
            ));

            return $invoice->fresh();
        });
    }
}
