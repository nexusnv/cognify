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

class ScheduleApPaymentHandoff
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
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $scheduledForDate, $paymentReference): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Exported) {
                throw new ConflictHttpException('Only exported AP payment handoffs can be scheduled.');
            }

            $handoff->assertLockVersion($lockVersion);

            $invoices = $handoff->invoices()->lockForUpdate()->get();
            if ($invoices->isEmpty()) {
                throw new ConflictHttpException('AP payment handoff must include at least one invoice.');
            }

            $before = $handoff->only(['status', 'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Scheduled,
                'scheduled_by_user_id' => $actor->id,
                'scheduled_at' => now(),
                'scheduled_for_date' => $scheduledForDate,
                'payment_reference' => $paymentReference,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

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
                    metadata: ['handoffId' => (string) $handoff->id, 'handoffNumber' => $handoff->number],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.scheduled',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Exported->value,
                    'toStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'scheduledForDate' => $scheduledForDate,
                    'paymentReference' => $paymentReference,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
