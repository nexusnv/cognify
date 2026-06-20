<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkApPaymentHandoffPaid
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly PaymentAllocationSumCalculator $calculator,
    ) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        ?string $remittanceReference = null,
        ?string $remittanceAdviceSentAt = null,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $remittanceReference, $remittanceAdviceSentAt): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Only scheduled AP payment handoffs can be marked paid.');
            }

            $handoff->assertLockVersion($lockVersion);

            $invoices = $handoff->invoices()->lockForUpdate()->get();
            $underAllocated = [];

            foreach ($invoices as $invoice) {
                $allocated = $this->calculator->sumForInvoice($invoice);
                if (bccomp($allocated, (string) $invoice->total_amount, 4) !== 0) {
                    $underAllocated[] = [
                        'invoiceId' => (string) $invoice->id,
                        'invoiceNumber' => $invoice->invoice_number,
                        'allocated' => $allocated,
                        'total' => (string) $invoice->total_amount,
                        'remaining' => bcsub((string) $invoice->total_amount, $allocated, 4),
                    ];
                }
            }

            if (! empty($underAllocated)) {
                throw new ConflictHttpException('One or more invoices are under-allocated. Add allocations or use Close with variance.');
            }

            $before = $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'remittance_reference', 'remittance_advice_sent_at', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Paid,
                'paid_by_user_id' => $actor->id,
                'paid_at' => now(),
                'remittance_reference' => $remittanceReference,
                'remittance_advice_sent_at' => $remittanceAdviceSentAt,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::Paid,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.paid',
                    subject: $invoice,
                    metadata: ['handoffId' => (string) $handoff->id, 'handoffNumber' => $handoff->number],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.paid',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'remittance_reference', 'remittance_advice_sent_at', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'toStatus' => ApPaymentHandoffStatus::Paid->value,
                    'remittanceReference' => $remittanceReference,
                    'remittanceAdviceSentAt' => $remittanceAdviceSentAt,
                ],
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment.remitted',
                subject: $handoff,
                metadata: [
                    'remittanceReference' => $remittanceReference,
                    'remittanceAdviceSentAt' => $remittanceAdviceSentAt,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
