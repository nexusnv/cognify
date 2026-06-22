<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationNormalizationAttachment;
use Domains\Quotation\Models\QuotationNormalizationField;
use Domains\Quotation\Models\QuotationNormalizationIssue;
use Domains\Quotation\Models\QuotationNormalizationLineGroup;
use Domains\Quotation\Models\QuotationNormalizationLineMapping;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationIssueStatus;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Domains\Quotation\States\QuotationNormalizationPricingMode;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\Support\QuotationNormalizationIssueCatalog;
use Domains\Quotation\Support\QuotationNormalizationProvenance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RunDeterministicQuotationNormalizer
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(Tenant $tenant, QuotationVersion $version, QuotationNormalization $normalization): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $version, $normalization): QuotationNormalization {
            $lockedVersion = QuotationVersion::query()
                ->with(['quotation.rfq', 'lineItems' => fn ($query) => $query->orderBy('position')])
                ->whereKey($version->id)
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedNormalization = QuotationNormalization::query()
                ->whereKey($normalization->id)
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedNormalization->quotation_version_id !== (int) $lockedVersion->id) {
                throw new InvalidArgumentException('Quotation normalization must belong to the same tenant and source version.');
            }

            $this->resetGeneratedState($lockedNormalization);

            $currency = $this->normalizeCurrency($lockedVersion->currency);
            $lineItems = $lockedVersion->lineItems->values();
            $rfqLineItems = collect($lockedVersion->quotation?->rfq?->line_items ?? [])->values();

            if ($currency === null) {
                $this->recordIssue(
                    $lockedNormalization,
                    QuotationNormalizationIssueSeverity::Blocking,
                    'manualEntry.currency',
                    QuotationNormalizationIssueCatalog::MISSING_CURRENCY,
                    'Currency is required before the quotation can be compared.',
                    $lockedVersion->currency,
                    ['currency' => 'ISO 4217 code'],
                );
            } elseif (! preg_match('/^[A-Z]{3}$/', $currency)) {
                $this->recordIssue(
                    $lockedNormalization,
                    QuotationNormalizationIssueSeverity::Blocking,
                    'manualEntry.currency',
                    QuotationNormalizationIssueCatalog::INVALID_CURRENCY,
                    'Currency must be a valid 3-letter ISO 4217 code.',
                    $lockedVersion->currency,
                    ['currency' => 'ISO 4217 code'],
                );
            } else {
                $this->recordField($lockedNormalization, $lockedVersion, 'manualEntry.currency', $lockedVersion->currency, $currency, $currency, $currency, 'text');
            }

            $amountFields = [
                'manualEntry.subtotalAmount' => $lockedVersion->subtotal_amount,
                'manualEntry.taxAmount' => $lockedVersion->tax_amount,
                'manualEntry.freightAmount' => $lockedVersion->freight_amount,
                'manualEntry.discountAmount' => $lockedVersion->discount_amount,
                'manualEntry.totalAmount' => $lockedVersion->total_amount,
            ];

            foreach ($amountFields as $fieldPath => $value) {
                $normalizedValue = $this->decimalString($value);
                $this->recordField(
                    $lockedNormalization,
                    $lockedVersion,
                    $fieldPath,
                    $value,
                    $normalizedValue,
                    $currency,
                    $normalizedValue,
                    'decimal',
                );
            }

            $textFields = [
                'manualEntry.paymentTerms' => $lockedVersion->payment_terms,
                'manualEntry.deliveryTerms' => $lockedVersion->delivery_terms,
                'manualEntry.warrantyTerms' => $lockedVersion->warranty_terms,
                'manualEntry.exclusions' => $lockedVersion->exclusions,
                'manualEntry.complianceNotes' => $lockedVersion->compliance_notes,
            ];

            foreach ($textFields as $fieldPath => $value) {
                if ($value !== null && trim((string) $value) !== '') {
                    $this->recordField(
                        $lockedNormalization,
                        $lockedVersion,
                        $fieldPath,
                        $value,
                        (string) $value,
                        null,
                        (string) $value,
                        'text',
                    );
                }
            }

            $leadTime = $lockedVersion->lead_time_days;
            if ($leadTime !== null) {
                $this->recordField(
                    $lockedNormalization,
                    $lockedVersion,
                    'manualEntry.leadTimeDays',
                    $leadTime,
                    (int) $leadTime,
                    null,
                    (int) $leadTime,
                    'integer',
                );
            }

            $this->recordField(
                $lockedNormalization,
                $lockedVersion,
                'completeness.lineItemCount',
                $lineItems->count(),
                $lineItems->count(),
                null,
                $lineItems->count(),
                'integer',
            );

            $attachmentSnapshots = collect($lockedVersion->attachment_snapshots ?? []);
            $attachmentSnapshots->each(function (array $snapshot, int $index) use ($lockedNormalization): void {
                QuotationNormalizationAttachment::query()->create([
                    'tenant_id' => $lockedNormalization->tenant_id,
                    'normalization_id' => $lockedNormalization->id,
                    'quotation_version_attachment_id' => (string) ($snapshot['id'] ?? ''),
                    'filename' => (string) ($snapshot['filename'] ?? 'attachment'),
                    'mime_type' => $snapshot['mimeType'] ?? null,
                    'extension' => $snapshot['extension'] ?? null,
                    'size_bytes' => $snapshot['sizeBytes'] ?? null,
                    'checksum_sha256' => $snapshot['checksumSha256'] ?? null,
                    'available' => (bool) ($snapshot['available'] ?? true),
                    'source' => 'quotation_version',
                    'uploaded_at' => isset($snapshot['createdAt']) ? $this->parseTimestamp((string) $snapshot['createdAt']) : null,
                    'evidence_role' => 'source_attachment',
                    'issue_summary' => $snapshot['checksumSha256'] === null || $snapshot['checksumSha256'] === ''
                        ? 'Checksum unavailable.'
                        : null,
                ]);

                if (($snapshot['checksumSha256'] ?? null) === null || trim((string) ($snapshot['checksumSha256'] ?? '')) === '') {
                    $this->recordIssue(
                        $lockedNormalization,
                        QuotationNormalizationIssueSeverity::Warning,
                        "attachments.{$index}.checksumSha256",
                        QuotationNormalizationIssueCatalog::ATTACHMENT_CHECKSUM_UNAVAILABLE,
                        'Attachment checksum is unavailable.',
                        $snapshot,
                        ['checksumSha256' => 'sha256'],
                    );
                }
            });

            $mappedRfQLineIds = collect();
            if ($lineItems->isEmpty()) {
                $this->recordIssue(
                    $lockedNormalization,
                    QuotationNormalizationIssueSeverity::Blocking,
                    'lineGroups',
                    QuotationNormalizationIssueCatalog::MISSING_COMPARABLE_LINE_ITEMS,
                    'No comparable quotation version line items are available for normalization.',
                    null,
                    ['lineGroups' => 'per_line mappings'],
                );
            } else {
                $lineItems->each(function (QuotationVersionLineItem $lineItem, int $index) use ($lockedNormalization, $currency, $mappedRfQLineIds): void {
                    $group = QuotationNormalizationLineGroup::query()->create([
                        'tenant_id' => $lockedNormalization->tenant_id,
                        'normalization_id' => $lockedNormalization->id,
                        'group_number' => $index + 1,
                        'pricing_mode' => QuotationNormalizationPricingMode::PerLine,
                        'description' => $lineItem->description,
                        'currency' => $currency,
                        'bundle_total_amount' => $this->decimalString($lineItem->total_amount ?? $lineItem->subtotal_amount),
                        'notes' => $lineItem->notes,
                    ]);

                    QuotationNormalizationLineMapping::query()->create([
                        'tenant_id' => $lockedNormalization->tenant_id,
                        'quotation_normalization_line_group_id' => $group->id,
                        'rfq_line_item_id' => $lineItem->rfq_line_item_id,
                        'quotation_version_line_item_id' => $lineItem->id,
                        'mapping_type' => QuotationNormalizationMappingType::Full,
                        'quantity' => $lineItem->quantity,
                        'unit' => $lineItem->unit,
                        'unit_price' => $this->decimalString($lineItem->unit_price),
                        'line_total' => $this->decimalString($lineItem->total_amount ?? $lineItem->subtotal_amount),
                        'buyer_note' => null,
                    ]);

                    if ($lineItem->rfq_line_item_id !== null) {
                        $mappedRfQLineIds->push((string) $lineItem->rfq_line_item_id);
                    }
                });

                $requiredRfQLineIds = $rfqLineItems->pluck('id')->filter()->map(fn ($id) => (string) $id)->values();

                $requiredRfQLineIds
                    ->diff($mappedRfQLineIds->unique()->values())
                    ->each(function (string $rfqLineId) use ($lockedNormalization): void {
                        $this->recordIssue(
                            $lockedNormalization,
                            QuotationNormalizationIssueSeverity::Blocking,
                            'lineGroups',
                            QuotationNormalizationIssueCatalog::REQUIRED_RFQ_LINE_UNMAPPED,
                            'A required RFQ line item is not mapped to the quotation version.',
                            ['rfqLineItemId' => $rfqLineId],
                            ['rfqLineItemId' => $rfqLineId],
                        );
                    });
            }

            $paymentTerms = $lockedVersion->payment_terms;
            if (is_string($paymentTerms) && trim($paymentTerms) !== '') {
                $this->recordIssue(
                    $lockedNormalization,
                    QuotationNormalizationIssueSeverity::Warning,
                    'manualEntry.paymentTerms',
                    QuotationNormalizationIssueCatalog::PAYMENT_TERMS_UNSTRUCTURED,
                    'Payment terms are free text and require review.',
                    $paymentTerms,
                    ['paymentTerms' => $paymentTerms],
                );
            }

            if ($lockedVersion->warranty_terms === null || trim((string) $lockedVersion->warranty_terms) === '') {
                $this->recordIssue(
                    $lockedNormalization,
                    QuotationNormalizationIssueSeverity::Warning,
                    'manualEntry.warrantyTerms',
                    QuotationNormalizationIssueCatalog::WARRANTY_TERMS_MISSING,
                    'Warranty terms are missing.',
                    $lockedVersion->warranty_terms,
                    ['warrantyTerms' => 'text'],
                );
            }

            if ($lockedVersion->subtotal_amount !== null
                && $lockedVersion->tax_amount !== null
                && $lockedVersion->freight_amount !== null
                && $lockedVersion->discount_amount !== null
                && $lockedVersion->total_amount !== null) {
                $expectedTotal = round(((float) $lockedVersion->subtotal_amount + (float) $lockedVersion->tax_amount + (float) $lockedVersion->freight_amount) - (float) $lockedVersion->discount_amount, 2);
                $actualTotal = round((float) $lockedVersion->total_amount, 2);

                if (abs($expectedTotal - $actualTotal) >= 0.01) {
                    $this->recordIssue(
                        $lockedNormalization,
                        QuotationNormalizationIssueSeverity::Blocking,
                        'manualEntry.totalAmount',
                        QuotationNormalizationIssueCatalog::TOTAL_RECONCILIATION_MISMATCH,
                        'The quotation total does not reconcile to the component amounts.',
                        [
                            'subtotalAmount' => $lockedVersion->subtotal_amount,
                            'taxAmount' => $lockedVersion->tax_amount,
                            'freightAmount' => $lockedVersion->freight_amount,
                            'discountAmount' => $lockedVersion->discount_amount,
                            'totalAmount' => $lockedVersion->total_amount,
                        ],
                        ['totalAmount' => number_format($expectedTotal, 2, '.', '')],
                    );
                }
            }

            if ($lockedVersion->total_amount === null) {
                $this->recordIssue(
                    $lockedNormalization,
                    QuotationNormalizationIssueSeverity::Blocking,
                    'manualEntry.totalAmount',
                    QuotationNormalizationIssueCatalog::MISSING_TOTAL_AMOUNT,
                    'Total amount is required before the quotation can be compared.',
                    $lockedVersion->total_amount,
                    ['totalAmount' => 'decimal'],
                );
            }

            $blockingIssueExists = $lockedNormalization->issues()
                ->where('severity', QuotationNormalizationIssueSeverity::Blocking->value)
                ->where('status', QuotationNormalizationIssueStatus::Open->value)
                ->exists();

            $lockedNormalization->forceFill([
                'status' => $blockingIssueExists ? QuotationNormalizationStatus::NeedsReview : QuotationNormalizationStatus::ReadyForApproval,
                'normalized_at' => now(),
                'last_job_error' => null,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: null,
                action: 'quotation_normalization.completed',
                subject: $lockedNormalization,
                metadata: [
                    'quotationId' => (string) $lockedVersion->quotation_id,
                    'quotationVersionId' => (string) $lockedVersion->id,
                    'normalizationId' => (string) $lockedNormalization->id,
                    'status' => $lockedNormalization->status->value,
                    'blockingIssueCount' => $lockedNormalization->issues()->where('severity', QuotationNormalizationIssueSeverity::Blocking->value)->count(),
                    'warningIssueCount' => $lockedNormalization->issues()->where('severity', QuotationNormalizationIssueSeverity::Warning->value)->count(),
                    'infoIssueCount' => $lockedNormalization->issues()->where('severity', QuotationNormalizationIssueSeverity::Info->value)->count(),
                ],
                subjectDisplay: $lockedVersion->quotation?->number,
            ));

            return $lockedNormalization->refresh()->load(['fields', 'attachments', 'issues', 'lineGroups.mappings']);
        });
    }

    private function resetGeneratedState(QuotationNormalization $normalization): void
    {
        $normalization->fields()->delete();
        $normalization->attachments()->delete();
        $normalization->lineGroups()->delete();
        $normalization->issues()
            ->where('status', QuotationNormalizationIssueStatus::Open->value)
            ->delete();
    }

    private function recordField(
        QuotationNormalization $normalization,
        QuotationVersion $version,
        string $fieldPath,
        mixed $rawValue,
        mixed $normalizedValue,
        ?string $currency,
        mixed $payload,
        string $dataType,
    ): void {
        QuotationNormalizationField::query()->create([
            'tenant_id' => $normalization->tenant_id,
            'normalization_id' => $normalization->id,
            'field_path' => $fieldPath,
            'raw_value' => ['value' => $rawValue],
            'normalized_value' => ['value' => $normalizedValue],
            'data_type' => $dataType,
            'currency' => $currency,
            'confidence' => 1.0,
            'source' => 'quotation_version',
            'provenance' => QuotationNormalizationProvenance::field(
                $version,
                $fieldPath,
                ['value' => $rawValue],
                ['value' => $payload],
                'deterministic-v1',
            ),
        ]);
    }

    private function recordIssue(
        QuotationNormalization $normalization,
        QuotationNormalizationIssueSeverity $severity,
        string $fieldPath,
        string $issueCode,
        string $message,
        mixed $rawValue,
        mixed $suggestedValue,
    ): void {
        $issue = QuotationNormalizationIssue::query()->create([
            'tenant_id' => $normalization->tenant_id,
            'normalization_id' => $normalization->id,
            'severity' => $severity,
            'field_path' => $fieldPath,
            'issue_code' => $issueCode,
            'message' => $message,
            'raw_value' => $rawValue === null ? null : ['value' => $rawValue],
            'suggested_value' => $suggestedValue === null ? null : ['value' => $suggestedValue],
            'status' => QuotationNormalizationIssueStatus::Open,
        ]);

        $this->auditRecorder->record(new AuditEventData(
            tenant: $normalization->tenant,
            actor: null,
            action: 'quotation_normalization.issue_recorded',
            subject: $issue,
            metadata: [
                'quotationId' => (string) $normalization->quotation_id,
                'quotationVersionId' => (string) $normalization->quotation_version_id,
                'normalizationId' => (string) $normalization->id,
                'issueId' => (string) $issue->id,
                'issueCode' => $issueCode,
                'severity' => $severity->value,
                'fieldPath' => $fieldPath,
            ],
            subjectDisplay: $message,
        ));
    }

    private function decimalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function normalizeCurrency(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $currency = strtoupper(trim((string) $value));

        return $currency === '' ? null : $currency;
    }

    private function parseTimestamp(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
