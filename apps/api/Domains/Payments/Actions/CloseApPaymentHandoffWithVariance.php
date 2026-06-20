<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Payments\Models\ApPaymentAllocation;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CloseApPaymentHandoffWithVariance
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly PaymentAllocationSumCalculator $calculator,
    ) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        string $varianceReason,
        ?string $remittanceReference = null,
        ?string $remittanceAdviceSentAt = null,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $varianceReason, $remittanceReference, $remittanceAdviceSentAt): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Only scheduled AP payment handoffs can be closed with variance.');
            }

            $handoff->assertLockVersion($lockVersion);

            $varianceReason = trim($varianceReason);
            if (strlen($varianceReason) < 5) {
                throw ValidationException::withMessages([
                    'varianceReason' => 'Variance reason must be at least 5 characters.',
                ]);
            }

            $allocationCount = ApPaymentAllocation::query()
                ->where('ap_payment_handoff_id', $handoff->id)
                ->whereNull('voided_at')
                ->count();

            if ($allocationCount === 0) {
                throw new ConflictHttpException('Cannot close with variance when no allocations exist. Use Mark Failed instead.');
            }

            $invoices = $handoff->invoices()->lockForUpdate()->get();
            $totalAllocated = '0.0000';

            foreach ($invoices as $invoice) {
                $totalAllocated = bcadd($totalAllocated, $this->calculator->sumForInvoice($invoice), 4);
            }

            $varianceAmount = bcsub((string) $handoff->total_amount, $totalAllocated, 4);

            $before = $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'variance_amount', 'variance_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Paid,
                'paid_by_user_id' => $actor->id,
                'paid_at' => now(),
                'variance_amount' => $varianceAmount,
                'variance_reason' => $varianceReason,
                'variance_closed_by_user_id' => $actor->id,
                'variance_closed_at' => now(),
                'remittance_reference' => $remittanceReference,
                'remittance_advice_sent_at' => $remittanceAdviceSentAt,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            foreach ($invoices as $invoice) {
                $allocated = $this->calculator->sumForInvoice($invoice);
                $isFullyPaid = bccomp($allocated, (string) $invoice->total_amount, 4) === 0;

                $newStatus = $isFullyPaid
                    ? SupplierInvoicePaymentStatus::Paid
                    : SupplierInvoicePaymentStatus::PartiallyPaid;

                $invoice->forceFill([
                    'payment_status' => $newStatus,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $action = $isFullyPaid ? 'supplier_invoice.paid' : 'supplier_invoice.partially_paid';
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: $action,
                    subject: $invoice,
                    metadata: [
                        'handoffId' => (string) $handoff->id,
                        'handoffNumber' => $handoff->number,
                        'allocatedAmount' => $allocated,
                        'invoiceTotal' => (string) $invoice->total_amount,
                    ],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.paid_with_variance',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'variance_amount', 'variance_reason', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'toStatus' => ApPaymentHandoffStatus::Paid->value,
                    'varianceAmount' => $varianceAmount,
                    'varianceReason' => $varianceReason,
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
