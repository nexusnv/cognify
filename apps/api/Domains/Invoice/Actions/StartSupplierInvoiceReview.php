<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StartSupplierInvoiceReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(SupplierInvoice $supplierInvoice, User $actor, int $lockVersion): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion): SupplierInvoice {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice->assertLockVersion($lockVersion);
            $previousStatus = $invoice->statusState();

            if (! in_array($previousStatus, [SupplierInvoiceStatus::Captured, SupplierInvoiceStatus::NeedsInformation], true)) {
                throw new ConflictHttpException('Supplier invoice review can only start from captured or needs-information status.');
            }

            $before = $invoice->only([
                'status',
                'review_started_by_user_id',
                'review_started_at',
                'lock_version',
            ]);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::InReview,
                'review_started_by_user_id' => $actor->id,
                'review_started_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.review_started',
                subject: $invoice,
                metadata: [
                    'invoiceNumber' => $invoice->invoice_number,
                    'invoiceId' => (string) $invoice->id,
                    'purchaseOrderId' => (string) $invoice->purchase_order_id,
                    'purchaseOrderNumber' => $invoice->purchaseOrder?->number,
                    'vendorId' => $invoice->vendor_id !== null ? (string) $invoice->vendor_id : null,
                    'previousStatus' => $previousStatus->value,
                    'nextStatus' => SupplierInvoiceStatus::InReview->value,
                ],
                before: $before,
                after: $invoice->only([
                    'status',
                    'review_started_by_user_id',
                    'review_started_at',
                    'lock_version',
                ]),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor'])->loadCount('attachments');
        });
    }
}
