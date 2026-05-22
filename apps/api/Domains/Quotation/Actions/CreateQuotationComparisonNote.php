<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;

class CreateQuotationComparisonNote
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): QuotationComparisonNote
    {
        $note = QuotationComparisonNote::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $data['quotationId'] ?? null,
            'vendor_id' => $data['vendorId'] ?? null,
            'rfq_line_item_id' => $data['rfqLineItemId'] ?? null,
            'section' => $data['section'],
            'note' => trim($data['note']),
            'created_by_user_id' => $actor->id,
        ]);

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'quotation_comparison.note_created',
            subject: $rfq,
            metadata: $this->metadata($note),
            subjectDisplay: $rfq->number,
        ));

        return $note->refresh()->load('createdBy');
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(QuotationComparisonNote $note): array
    {
        return [
            'noteId' => (string) $note->id,
            'section' => $note->section?->value ?? $note->section,
            'quotationId' => $note->quotation_id !== null ? (string) $note->quotation_id : null,
            'vendorId' => $note->vendor_id !== null ? (string) $note->vendor_id : null,
            'rfqLineItemId' => $note->rfq_line_item_id,
        ];
    }
}
