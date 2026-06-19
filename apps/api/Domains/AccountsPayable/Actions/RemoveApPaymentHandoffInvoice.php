<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RemoveApPaymentHandoffInvoice
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly BuildApPaymentHandoffSnapshot $buildSnapshot,
    ) {}

    public function handle(ApPaymentHandoff $handoff, SupplierInvoice $invoice, User $actor, int $lockVersion): ApPaymentHandoff
    {
        return DB::transaction(function () use ($handoff, $invoice, $actor, $lockVersion): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Draft) {
                throw new ConflictHttpException('Only draft AP payment handoffs can have invoices removed.');
            }

            $handoff->assertLockVersion($lockVersion);

            $detachCount = $handoff->invoices()->detach($invoice->id);

            if ($detachCount === 0) {
                throw new ConflictHttpException('Invoice is not attached to this AP payment handoff.');
            }

            $remainingInvoices = $handoff->invoices()->with(['vendor'])->lockForUpdate()->get();
            $totalAmount = $remainingInvoices->reduce(
                fn (string $carry, $inv): string => bcadd($carry, (string) ($inv->total_amount ?? '0'), 2),
                '0.00',
            );

            $snapshotData = $this->buildSnapshot->handle($remainingInvoices, [
                'currency' => $handoff->currency,
                'totalAmount' => (string) $totalAmount,
                'invoiceCount' => $remainingInvoices->count(),
            ]);

            $before = $handoff->only(['total_amount', 'snapshot', 'readiness_warnings', 'lock_version']);

            $handoff->forceFill([
                'total_amount' => $totalAmount,
                'snapshot' => $snapshotData->toArray(),
                'readiness_warnings' => $snapshotData->readinessWarnings,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.invoice_removed',
                subject: $handoff,
                metadata: ['invoiceId' => (string) $invoice->id, 'invoiceNumber' => $invoice->invoice_number],
                before: $before,
                after: $handoff->only(['total_amount', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}
