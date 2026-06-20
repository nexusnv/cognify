<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Payments\Models\ApPaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VoidApPaymentHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        string $voidReason,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $voidReason): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if (! in_array($handoff->statusState(), [ApPaymentHandoffStatus::Scheduled, ApPaymentHandoffStatus::Paid], true)) {
                throw new ConflictHttpException('Only scheduled or paid AP payment handoffs can be voided.');
            }

            $handoff->assertLockVersion($lockVersion);

            $voidReason = trim($voidReason);
            if (strlen($voidReason) < 5) {
                throw ValidationException::withMessages([
                    'voidReason' => 'Void reason must be at least 5 characters.',
                ]);
            }

            $before = $handoff->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Voided,
                'voided_by_user_id' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $voidReason,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            ApPaymentAllocation::query()
                ->where('ap_payment_handoff_id', $handoff->id)
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);

            $invoices = $handoff->invoices()->lockForUpdate()->get();

            foreach ($invoices as $invoice) {
                $previousPaymentStatus = $invoice->payment_status instanceof SupplierInvoicePaymentStatus
                    ? $invoice->payment_status->value
                    : ($invoice->payment_status ?? null);

                // CRITICAL: invoice column goes DIRECTLY to handoff_exported, NEVER to payment_voided.
                // The payment_voided event is captured in the audit event payload only.
                // This avoids wasteful double-writes and prevents accidental side effects.
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::HandoffExported,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.payment_voided',
                    subject: $invoice,
                    metadata: [
                        'handoffId' => (string) $handoff->id,
                        'handoffNumber' => $handoff->number,
                        'fromStatus' => $previousPaymentStatus,
                        'toStatus' => SupplierInvoicePaymentStatus::HandoffExported->value,
                        'voidReason' => $voidReason,
                    ],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.voided',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']),
                metadata: [
                    'fromStatus' => $before['status'],
                    'toStatus' => ApPaymentHandoffStatus::Voided->value,
                    'voidReason' => $voidReason,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
