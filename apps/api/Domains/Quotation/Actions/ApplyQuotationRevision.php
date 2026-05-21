<?php

namespace Domains\Quotation\Actions;

use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
use Illuminate\Validation\ValidationException;

class ApplyQuotationRevision
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, int|string>  $attachmentIds
     * @param  array{name: string|null, email: string|null}|null  $submittedByVendorContact
     */
    public function handle(
        Tenant $tenant,
        Quotation $quotation,
        array $payload,
        QuotationSubmissionSource $source,
        ?int $actorId = null,
        ?array $submittedByVendorContact = null,
        array $attachmentIds = [],
    ): void {
        $this->ensureAttachmentIdsBelongToQuotation($tenant, $quotation, $attachmentIds);

        $lineItems = collect($payload['lineItems'] ?? [])->values();
        $missingFields = $this->missingFields($payload, $lineItems->count());

        $quotation->forceFill([
            'quotation_reference' => $payload['quotationReference'] ?? null,
            'status' => QuotationStatus::Received->value,
            'submission_source' => $quotation->submission_source ?? $source->value,
            'submitted_at' => $quotation->submitted_at ?? now(),
            'submitted_by_user_id' => $quotation->submitted_by_user_id ?? $actorId,
            'submitted_by_vendor_contact' => $quotation->submitted_by_vendor_contact ?? $submittedByVendorContact,
            'quoted_at' => $payload['quotedAt'] ?? null,
            'valid_until' => $payload['validUntil'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'subtotal_amount' => $payload['subtotalAmount'] ?? null,
            'tax_amount' => $payload['taxAmount'] ?? null,
            'freight_amount' => $payload['freightAmount'] ?? null,
            'discount_amount' => $payload['discountAmount'] ?? null,
            'total_amount' => $payload['totalAmount'] ?? null,
            'payment_terms' => $payload['paymentTerms'] ?? null,
            'delivery_terms' => $payload['deliveryTerms'] ?? null,
            'lead_time_days' => $payload['leadTimeDays'] ?? null,
            'warranty_terms' => $payload['warrantyTerms'] ?? null,
            'exclusions' => $payload['exclusions'] ?? null,
            'compliance_notes' => $payload['complianceNotes'] ?? null,
            'buyer_notes' => $source === QuotationSubmissionSource::BuyerUpload
                ? ($payload['buyerNotes'] ?? null)
                : $quotation->buyer_notes,
            'vendor_notes' => $payload['vendorNotes'] ?? null,
            'manual_entry_complete' => $missingFields === [],
            'manual_entry_missing_fields' => $missingFields,
            'manual_entry_saved_at' => now(),
            'manual_entry_saved_source' => $source->value,
            'latest_received_at' => now(),
        ])->save();

        QuotationLineItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('quotation_id', $quotation->id)
            ->delete();

        $lineItems->each(function (array $lineItem, int $index) use ($tenant, $quotation): void {
            QuotationLineItem::query()->create([
                'tenant_id' => $tenant->id,
                'quotation_id' => $quotation->id,
                'rfq_line_item_id' => $lineItem['rfqLineItemId'] ?? null,
                'description' => $lineItem['description'],
                'quantity' => $lineItem['quantity'],
                'unit' => $lineItem['unit'] ?? null,
                'unit_price' => $lineItem['unitPrice'] ?? null,
                'subtotal_amount' => $lineItem['subtotalAmount'] ?? null,
                'tax_amount' => $lineItem['taxAmount'] ?? null,
                'total_amount' => $lineItem['totalAmount'] ?? null,
                'lead_time_days' => $lineItem['leadTimeDays'] ?? null,
                'manufacturer' => $lineItem['manufacturer'] ?? null,
                'model_number' => $lineItem['modelNumber'] ?? null,
                'alternate_offered' => $lineItem['alternateOffered'] ?? false,
                'compliance_status' => $lineItem['complianceStatus'] ?? null,
                'notes' => $lineItem['notes'] ?? null,
                'position' => $index + 1,
            ]);
        });
    }

    /**
     * @param  array<int, int|string>  $attachmentIds
     */
    private function ensureAttachmentIdsBelongToQuotation(Tenant $tenant, Quotation $quotation, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $expectedIds = collect($attachmentIds)->map(fn (int|string $id) => (string) $id)->unique()->values();
        $validCount = Attachment::query()
            ->where('tenant_id', $tenant->id)
            ->where('attachable_type', Quotation::class)
            ->where('attachable_id', $quotation->id)
            ->whereIn('id', $expectedIds->all())
            ->count();

        if ($validCount !== $expectedIds->count()) {
            throw ValidationException::withMessages([
                'attachmentIds' => ['One or more selected attachments do not belong to this quotation.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingFields(array $payload, int $lineItemCount): array
    {
        return collect([
            blank($payload['currency'] ?? null) ? 'currency' : null,
            blank($payload['totalAmount'] ?? null) ? 'totalAmount' : null,
            $lineItemCount === 0 ? 'lineItems' : null,
        ])->filter()->values()->all();
    }
}
