<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;

class UpdateQuotationComparisonNote
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, QuotationComparisonNote $note, array $data): QuotationComparisonNote
    {
        $before = $this->snapshot($note);

        $note->forceFill([
            'quotation_id' => $data['quotationId'] ?? null,
            'vendor_id' => $data['vendorId'] ?? null,
            'rfq_line_item_id' => $data['rfqLineItemId'] ?? null,
            'section' => $data['section'],
            'note' => trim($data['note']),
            'updated_by_user_id' => $actor->id,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'quotation_comparison.note_updated',
            subject: $rfq,
            metadata: ['noteId' => (string) $note->id],
            before: $before,
            after: $this->snapshot($note->refresh()),
            subjectDisplay: $rfq->number,
        ));

        return $note->load('createdBy');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(QuotationComparisonNote $note): array
    {
        return [
            'section' => $note->section?->value ?? $note->section,
            'note' => $note->note,
            'quotationId' => $note->quotation_id !== null ? (string) $note->quotation_id : null,
            'vendorId' => $note->vendor_id !== null ? (string) $note->vendor_id : null,
            'rfqLineItemId' => $note->rfq_line_item_id,
        ];
    }
}
