<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Data\SupplierInvoiceReviewChecklistData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CompleteSupplierInvoiceReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $checklist
     */
    public function handle(SupplierInvoice $supplierInvoice, User $actor, int $lockVersion, ?string $notes, array $checklist): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $notes, $checklist): SupplierInvoice {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('review', $invoice);

            if ($invoice->statusState() !== SupplierInvoiceStatus::InReview) {
                throw new ConflictHttpException('Supplier invoice review can only be completed while in review.');
            }

            $invoice->assertLockVersion($lockVersion);

            $normalizedChecklist = SupplierInvoiceReviewChecklistData::normalize($checklist);

            if (! SupplierInvoiceReviewChecklistData::allPassed($normalizedChecklist)) {
                throw new InvalidArgumentException('Complete review requires every checklist item to pass.');
            }

            $trimmedNotes = is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
            $before = $invoice->only([
                'status',
                'review_notes',
                'review_checklist',
                'review_blockers',
                'reviewed_by_user_id',
                'reviewed_at',
                'lock_version',
            ]);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Reviewed,
                'review_notes' => $trimmedNotes,
                'review_checklist' => $normalizedChecklist,
                'review_blockers' => [],
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.review_completed',
                subject: $invoice,
                metadata: [
                    'invoiceNumber' => $invoice->invoice_number,
                    'invoiceId' => (string) $invoice->id,
                    'purchaseOrderId' => (string) $invoice->purchase_order_id,
                    'purchaseOrderNumber' => $invoice->purchaseOrder?->number,
                    'vendorId' => $invoice->vendor_id !== null ? (string) $invoice->vendor_id : null,
                    'previousStatus' => SupplierInvoiceStatus::InReview->value,
                    'nextStatus' => SupplierInvoiceStatus::Reviewed->value,
                    'checklistSummary' => SupplierInvoiceReviewChecklistData::summary($normalizedChecklist),
                    'reviewBlockerCount' => count(SupplierInvoiceReviewChecklistData::blockers($normalizedChecklist)),
                    'notesProvided' => $trimmedNotes !== null,
                ],
                before: $before,
                after: $invoice->only([
                    'status',
                    'review_notes',
                    'review_checklist',
                    'review_blockers',
                    'reviewed_by_user_id',
                    'reviewed_at',
                    'lock_version',
                ]),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor'])->loadCount('attachments');
        });
    }
}
