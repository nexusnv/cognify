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
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

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

            $handoff->invoices()->detach($invoice->id);

            $remainingInvoices = $handoff->invoices()->get();
            $totalAmount = $remainingInvoices->sum(fn ($inv) => (float) ($inv->total_amount ?? 0));

            $before = $handoff->only(['total_amount', 'lock_version']);

            $handoff->forceFill([
                'total_amount' => $totalAmount,
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
