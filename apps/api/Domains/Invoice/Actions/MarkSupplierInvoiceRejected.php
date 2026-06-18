<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkSupplierInvoiceRejected
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        User $actor,
        int $lockVersion,
        string $reason,
    ): SupplierInvoice {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $reason) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::InApproval) {
                throw new ConflictHttpException('Supplier invoice can only be rejected from in-approval status.');
            }

            $invoice->assertLockVersion($lockVersion);

            $before = $invoice->only(['status', 'rejected_by_user_id', 'rejected_at', 'rejected_reason', 'lock_version']);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Rejected,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => now(),
                'rejected_reason' => $reason,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.rejected',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'reason' => $reason,
                ],
                before: $before,
                after: $invoice->only(['status', 'rejected_by_user_id', 'rejected_at', 'rejected_reason', 'lock_version']),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
