<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Data\SupplierInvoiceReviewChecklistData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkSupplierInvoiceNeedsInformation
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $checklist
     */
    public function handle(SupplierInvoice $supplierInvoice, User $actor, int $lockVersion, string $notes, array $checklist): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $notes, $checklist): SupplierInvoice {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::InReview) {
                throw new ConflictHttpException('Supplier invoice can only be marked needs-information while in review.');
            }

            $invoice->assertLockVersion($lockVersion);

            $normalizedChecklist = SupplierInvoiceReviewChecklistData::normalize($checklist);
            $blockers = SupplierInvoiceReviewChecklistData::blockers($normalizedChecklist);
            $trimmedNotes = trim($notes);

            if ($trimmedNotes === '') {
                throw new InvalidArgumentException('Needs-information review requires a reviewer note.');
            }

            if ($blockers === []) {
                throw new InvalidArgumentException('Needs-information review requires at least one failed or attention-needed checklist item.');
            }

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
                'status' => SupplierInvoiceStatus::NeedsInformation,
                'review_notes' => $trimmedNotes,
                'review_checklist' => $normalizedChecklist,
                'review_blockers' => $blockers,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.needs_information',
                subject: $invoice,
                metadata: [
                    'invoiceNumber' => $invoice->invoice_number,
                    'purchaseOrderId' => (string) $invoice->purchase_order_id,
                    'vendorId' => $invoice->vendor_id !== null ? (string) $invoice->vendor_id : null,
                    'previousStatus' => SupplierInvoiceStatus::InReview->value,
                    'nextStatus' => SupplierInvoiceStatus::NeedsInformation->value,
                    'checklistSummary' => SupplierInvoiceReviewChecklistData::summary($normalizedChecklist),
                    'reviewBlockerCount' => count($blockers),
                    'notesProvided' => true,
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
