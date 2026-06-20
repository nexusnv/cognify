<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RescheduleFailedApPaymentHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        ?string $scheduledForDate = null,
        ?string $paymentReference = null,
        ?string $notes = null,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $scheduledForDate, $paymentReference, $notes): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Failed) {
                throw new ConflictHttpException('Only failed AP payment handoffs can be re-scheduled.');
            }

            $handoff->assertLockVersion($lockVersion);

            $before = $handoff->only(['status', 'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Scheduled,
                'failed_by_user_id' => null,
                'failed_at' => null,
                'failure_code' => null,
                'failure_reason' => null,
                'scheduled_by_user_id' => $actor->id,
                'scheduled_at' => now(),
                'scheduled_for_date' => $scheduledForDate,
                'payment_reference' => $paymentReference,
                'notes' => $notes ?? $handoff->notes,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $invoices = $handoff->invoices()->lockForUpdate()->get();

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::PaymentScheduled,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.payment_scheduled',
                    subject: $invoice,
                    metadata: ['handoffId' => (string) $handoff->id, 'handoffNumber' => $handoff->number, 'rescheduled' => true],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.rescheduled',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'scheduled_by_user_id', 'scheduled_at', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Failed->value,
                    'toStatus' => ApPaymentHandoffStatus::Scheduled->value,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
