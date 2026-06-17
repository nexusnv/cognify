<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkSupplierInvoiceApproved
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        User $actor,
        int $lockVersion,
        bool $isStp = false,
    ): SupplierInvoice {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $isStp) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($isStp) {
                if ($invoice->statusState() !== SupplierInvoiceStatus::ReadyForApproval) {
                    throw new ConflictHttpException('STP approval requires ready-for-approval status.');
                }
            } elseif ($invoice->statusState() !== SupplierInvoiceStatus::InApproval) {
                throw new ConflictHttpException('Supplier invoice can only be approved from in-approval status.');
            }

            $invoice->assertLockVersion($lockVersion);

            $before = $invoice->only(['status', 'approved_by_user_id', 'approved_at', 'lock_version']);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Approved,
                'approved_by_user_id' => $isStp ? null : $actor->id,
                'approved_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.approved',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'isStp' => $isStp,
                ],
                before: $before,
                after: $invoice->only(['status', 'approved_by_user_id', 'approved_at', 'lock_version']),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
