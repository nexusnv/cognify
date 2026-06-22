<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;

class DeleteQuotationComparisonNote
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, QuotationComparisonNote $note): void
    {
        $before = [
            'section' => $note->section?->value ?? $note->section,
            'note' => $note->note,
            'quotationId' => $note->quotation_id !== null ? (string) $note->quotation_id : null,
            'vendorId' => $note->vendor_id !== null ? (string) $note->vendor_id : null,
            'rfqLineItemId' => $note->rfq_line_item_id,
        ];

        $note->forceFill(['deleted_by_user_id' => $actor->id])->save();
        $note->delete();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'quotation_comparison.note_deleted',
            subject: $rfq,
            metadata: ['noteId' => (string) $note->id],
            before: $before,
            subjectDisplay: $rfq->number,
        ));
    }
}
