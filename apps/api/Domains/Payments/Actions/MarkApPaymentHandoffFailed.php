<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Payments\States\ApPaymentFailureCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkApPaymentHandoffFailed
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        ApPaymentFailureCode $failureCode,
        string $failureReason,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $failureCode, $failureReason): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Only scheduled AP payment handoffs can be marked failed.');
            }

            $handoff->assertLockVersion($lockVersion);

            $failureReason = trim($failureReason);
            if (strlen($failureReason) < 5) {
                throw ValidationException::withMessages([
                    'failureReason' => 'Failure reason must be at least 5 characters.',
                ]);
            }

            $before = $handoff->only(['status', 'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Failed,
                'failed_by_user_id' => $actor->id,
                'failed_at' => now(),
                'failure_code' => $failureCode->value,
                'failure_reason' => $failureReason,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $invoices = $handoff->invoices()->lockForUpdate()->get();

            foreach ($invoices as $invoice) {
                // CRITICAL: invoice column goes DIRECTLY to handoff_exported, NOT to payment_failed.
                // The payment_failed action is captured in the audit event payload only.
                // This avoids wasteful double-writes within the same transaction (which external
                // readers under read-committed isolation would never see) and prevents accidental
                // side effects from model boot observers or async dispatchers.
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::HandoffExported,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.payment_failed',
                    subject: $invoice,
                    metadata: [
                        'handoffId' => (string) $handoff->id,
                        'handoffNumber' => $handoff->number,
                        'fromStatus' => SupplierInvoicePaymentStatus::PaymentScheduled->value,
                        'toStatus' => SupplierInvoicePaymentStatus::HandoffExported->value,
                        'failureCode' => $failureCode->value,
                        'failureReason' => $failureReason,
                    ],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.failed',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'toStatus' => ApPaymentHandoffStatus::Failed->value,
                    'failureCode' => $failureCode->value,
                    'failureReason' => $failureReason,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
