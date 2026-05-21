<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Data\QuotationCompletenessData;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\QuotationStatus;
use Illuminate\Support\Facades\DB;

class SaveQuotationManualEntry
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreateOrRevealQuotationForInvitation $createOrRevealQuotationForInvitation,
        private readonly CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Tenant $tenant, ?User $actor, RfqInvitation $invitation, array $payload, QuotationSubmissionSource $source): Quotation
    {
        return DB::transaction(function () use ($tenant, $actor, $invitation, $payload, $source): Quotation {
            $result = $this->createOrRevealQuotationForInvitation->handle($tenant, $invitation, $actor);
            $quotation = $result['quotation'];
            $previousComplete = (bool) $quotation->manual_entry_complete;
            $lineItems = collect($payload['lineItems'] ?? [])->values();
            $completeness = $this->completeness($payload, $lineItems->count());

            $quotation->forceFill([
                'quotation_reference' => $payload['quotationReference'] ?? null,
                'status' => QuotationStatus::Received->value,
                'submission_source' => $quotation->submission_source ?? $source->value,
                'submitted_at' => $quotation->submitted_at ?? now(),
                'submitted_by_user_id' => $quotation->submitted_by_user_id ?? $actor?->id,
                'submitted_by_vendor_contact' => $quotation->submitted_by_vendor_contact
                    ?? ($source === QuotationSubmissionSource::VendorPortal ? [
                        'name' => $invitation->contact_name,
                        'email' => $invitation->contact_email,
                    ] : null),
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
                'buyer_notes' => $source === QuotationSubmissionSource::BuyerUpload ? ($payload['buyerNotes'] ?? null) : $quotation->buyer_notes,
                'vendor_notes' => $payload['vendorNotes'] ?? null,
                'manual_entry_complete' => $completeness->isComplete,
                'manual_entry_missing_fields' => $completeness->missingFields,
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

            $baseMetadata = [
                'source' => $source->value,
                'quotationId' => (string) $quotation->id,
                'rfqInvitationId' => (string) $invitation->id,
                'rfqId' => (string) $invitation->rfq_id,
                'vendorId' => (string) $invitation->vendor_id,
                'actor' => [
                    'type' => $actor === null ? 'vendor_portal' : 'user',
                    'id' => $actor?->id === null ? null : (string) $actor->id,
                ],
            ];

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation.manual_entry_saved',
                subject: $quotation,
                metadata: $baseMetadata + [
                    'changedFieldGroups' => ['header', 'commercial_terms', 'notes'],
                ],
                subjectDisplay: $quotation->number,
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation.line_items_saved',
                subject: $quotation,
                metadata: $baseMetadata + [
                    'changedFieldGroups' => ['line_items'],
                    'lineItemCount' => $lineItems->count(),
                ],
                subjectDisplay: $quotation->number,
            ));

            if ($previousComplete !== $completeness->isComplete) {
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'quotation.completeness_changed',
                    subject: $quotation,
                    metadata: $baseMetadata + $completeness->toArray() + [
                        'changedFieldGroups' => ['completeness'],
                    ],
                    subjectDisplay: $quotation->number,
                ));
            }

            $version = $this->createQuotationVersionSnapshot->handle(
                $tenant,
                $quotation,
                $actor,
                $source,
                null,
                ['trigger' => 'manual_entry_save'],
            );

            $quotation->forceFill([
                'current_version_id' => $version->id,
                'version_count' => $version->version_number,
            ])->save();

            $quotation = $quotation->refresh()->load([
                'attachments' => fn ($query) => $query->with('uploader')->latest('created_at'),
                'lineItems',
                'submittedByUser',
                'rfq',
                'vendor',
                'rfqInvitation',
                'currentVersion.lineItems',
            ]);

            $quotation->wasRecentlyCreated = false;

            return $quotation;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function completeness(array $payload, int $lineItemCount): QuotationCompletenessData
    {
        $missing = [];

        foreach (['currency', 'totalAmount'] as $field) {
            if (blank($payload[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        if ($lineItemCount === 0) {
            $missing[] = 'lineItems';
        }

        return new QuotationCompletenessData($missing === [], $missing, $lineItemCount);
    }
}
