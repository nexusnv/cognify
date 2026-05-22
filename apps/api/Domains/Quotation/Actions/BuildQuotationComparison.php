<?php

namespace Domains\Quotation\Actions;

use App\Tenancy\Tenant;
use Domains\Quotation\Http\Resources\QuotationComparisonNoteResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Support\Collection;

class BuildQuotationComparison
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Tenant $tenant, Rfq $rfq): array
    {
        $rfq->loadMissing(['requisition.requester', 'project', 'invitations.vendor']);

        $quotations = Quotation::query()
            ->with([
                'vendor',
                'currentVersion.currentNormalization.fields',
                'currentVersion.currentNormalization.lineGroups.mappings',
                'currentVersion.currentNormalization.issues',
            ])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->orderBy('vendor_id')
            ->orderBy('id')
            ->get();

        $notes = $rfq->comparisonNotes()
            ->with('createdBy')
            ->where('tenant_id', $tenant->id)
            ->latest('updated_at')
            ->latest('id')
            ->get();

        $vendors = $quotations->map(fn (Quotation $quotation): array => $this->vendorColumn($quotation, $notes))->values();
        $currencies = $vendors->pluck('currency')->filter()->unique()->values();

        return [
            'rfq' => $this->rfqSummary($rfq),
            'readiness' => [
                'responseCount' => $quotations->count(),
                'approvedNormalizationCount' => $vendors->where('readiness', 'ready')->count(),
                'pendingNormalizationCount' => $vendors->where('readiness', 'normalization_required')->count(),
                'missingResponseCount' => max(0, $rfq->invitations->count() - $quotations->count()),
                'mixedCurrency' => $currencies->count() > 1,
            ],
            'vendors' => $vendors->all(),
            'lineRows' => $this->lineRows($rfq, $quotations),
            'commercialTerms' => $this->commercialTerms($quotations),
            'notes' => QuotationComparisonNoteResource::collection($notes)->resolve(),
            'noteGroups' => $this->noteGroups($notes),
            'permissions' => [
                'canViewComparison' => true,
                'canManageQuotationComparisonNotes' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rfqSummary(Rfq $rfq): array
    {
        return [
            'id' => (string) $rfq->id,
            'number' => $rfq->number,
            'title' => $rfq->title,
            'status' => $rfq->status,
            'responseDueAt' => $rfq->response_due_at?->toISOString(),
            'scopeSummary' => $rfq->scope_summary,
            'requisition' => $rfq->requisition ? [
                'id' => (string) $rfq->requisition->id,
                'number' => $rfq->requisition->number,
                'title' => $rfq->requisition->title,
            ] : null,
            'project' => $rfq->project ? [
                'id' => (string) $rfq->project->id,
                'number' => $rfq->project->number,
                'name' => $rfq->project->name,
            ] : null,
        ];
    }

    /**
     * @param Collection<int, mixed> $notes
     * @return array<string, mixed>
     */
    private function vendorColumn(Quotation $quotation, Collection $notes): array
    {
        $normalization = $this->approvedNormalization($quotation);
        $fields = $normalization?->fields?->keyBy('field_path') ?? collect();

        return [
            'vendorId' => (string) $quotation->vendor_id,
            'vendorName' => $quotation->vendor?->name ?? 'Unknown vendor',
            'quotationId' => (string) $quotation->id,
            'quotationNumber' => $quotation->number,
            'quotationVersionId' => $quotation->current_version_id !== null ? (string) $quotation->current_version_id : null,
            'normalizationId' => $normalization !== null ? (string) $normalization->id : null,
            'normalizationRevision' => $normalization?->normalization_revision,
            'readiness' => $normalization === null ? 'normalization_required' : 'ready',
            'currency' => $this->field($fields, 'manualEntry.currency'),
            'totalAmount' => $this->field($fields, 'manualEntry.totalAmount'),
            'leadTimeDays' => $this->field($fields, 'manualEntry.leadTimeDays'),
            'paymentTerms' => $this->field($fields, 'manualEntry.paymentTerms'),
            'deliveryTerms' => $this->field($fields, 'manualEntry.deliveryTerms'),
            'warrantyTerms' => $this->field($fields, 'manualEntry.warrantyTerms'),
            'complianceNotes' => $this->field($fields, 'manualEntry.complianceNotes'),
            'issueCounts' => $this->issueCounts($normalization),
            'noteCount' => $notes
                ->filter(fn ($note): bool => $this->noteAppliesToVendor($note, $quotation))
                ->unique('id')
                ->count(),
            'links' => [
                'quotationVersion' => $quotation->current_version_id !== null ? "/quotations/{$quotation->id}/versions/{$quotation->current_version_id}" : null,
                'normalization' => $normalization !== null ? "/quotations/normalizations/{$normalization->id}" : null,
            ],
        ];
    }

    private function noteAppliesToVendor(mixed $note, Quotation $quotation): bool
    {
        if ($note->vendor_id !== null || $note->quotation_id !== null) {
            return (int) $note->vendor_id === (int) $quotation->vendor_id
                || (int) $note->quotation_id === (int) $quotation->id;
        }

        return $note->rfq_line_item_id !== null;
    }

    /**
     * @param Collection<int, Quotation> $quotations
     * @return array<int, array<string, mixed>>
     */
    private function lineRows(Rfq $rfq, Collection $quotations): array
    {
        return collect($rfq->line_items ?? [])->map(function (array $lineItem) use ($quotations): array {
            $lineItemId = (string) data_get($lineItem, 'id');

            return [
                'rfqLineItemId' => $lineItemId,
                'name' => data_get($lineItem, 'name'),
                'description' => data_get($lineItem, 'description') ?? data_get($lineItem, 'name'),
                'quantity' => data_get($lineItem, 'quantity'),
                'unit' => data_get($lineItem, 'unit_of_measure') ?? data_get($lineItem, 'unit'),
                'vendorCells' => $quotations->map(fn (Quotation $quotation): array => $this->vendorCell($quotation, $lineItemId))->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function vendorCell(Quotation $quotation, string $lineItemId): array
    {
        $normalization = $this->approvedNormalization($quotation);
        if ($normalization === null) {
            return ['vendorId' => (string) $quotation->vendor_id, 'readiness' => 'normalization_required', 'value' => null];
        }

        $mapping = $normalization->lineGroups
            ->flatMap(fn ($group) => $group->mappings->map(fn ($mapping) => [$group, $mapping]))
            ->first(fn ($pair) => (string) $pair[1]->rfq_line_item_id === $lineItemId);

        if ($mapping === null) {
            return ['vendorId' => (string) $quotation->vendor_id, 'readiness' => 'unmapped', 'value' => null];
        }

        [$group, $lineMapping] = $mapping;

        return [
            'vendorId' => (string) $quotation->vendor_id,
            'readiness' => 'ready',
            'description' => $group->description,
            'pricingMode' => $group->pricing_mode?->value ?? $group->pricing_mode,
            'currency' => $group->currency,
            'quantity' => $lineMapping->quantity,
            'unit' => $lineMapping->unit,
            'unitPrice' => $lineMapping->unit_price,
            'lineTotal' => $lineMapping->line_total,
            'bundleTotalAmount' => $group->bundle_total_amount,
            'buyerNote' => $lineMapping->buyer_note,
        ];
    }

    /**
     * @param Collection<int, Quotation> $quotations
     * @return array<int, array<string, mixed>>
     */
    private function commercialTerms(Collection $quotations): array
    {
        $terms = [
            ['id' => 'subtotalAmount', 'label' => 'Subtotal', 'fieldPath' => 'manualEntry.subtotalAmount'],
            ['id' => 'taxAmount', 'label' => 'Tax', 'fieldPath' => 'manualEntry.taxAmount'],
            ['id' => 'freightAmount', 'label' => 'Freight', 'fieldPath' => 'manualEntry.freightAmount'],
            ['id' => 'discountAmount', 'label' => 'Discount', 'fieldPath' => 'manualEntry.discountAmount'],
            ['id' => 'totalAmount', 'label' => 'Total', 'fieldPath' => 'manualEntry.totalAmount'],
            ['id' => 'validUntil', 'label' => 'Valid until', 'fieldPath' => 'manualEntry.validUntil'],
            ['id' => 'leadTimeDays', 'label' => 'Lead time', 'fieldPath' => 'manualEntry.leadTimeDays'],
            ['id' => 'paymentTerms', 'label' => 'Payment terms', 'fieldPath' => 'manualEntry.paymentTerms'],
            ['id' => 'deliveryTerms', 'label' => 'Delivery terms', 'fieldPath' => 'manualEntry.deliveryTerms'],
            ['id' => 'warrantyTerms', 'label' => 'Warranty', 'fieldPath' => 'manualEntry.warrantyTerms'],
            ['id' => 'exclusions', 'label' => 'Exclusions', 'fieldPath' => 'manualEntry.exclusions'],
            ['id' => 'complianceNotes', 'label' => 'Compliance notes', 'fieldPath' => 'manualEntry.complianceNotes'],
        ];

        return collect($terms)->map(fn (array $term): array => [
            'id' => $term['id'],
            'label' => $term['label'],
            'vendorValues' => $quotations->map(function (Quotation $quotation) use ($term): array {
                $normalization = $this->approvedNormalization($quotation);
                $fields = $normalization?->fields?->keyBy('field_path') ?? collect();

                return [
                    'vendorId' => (string) $quotation->vendor_id,
                    'value' => $normalization === null ? null : $this->field($fields, $term['fieldPath']),
                    'readiness' => $normalization === null ? 'normalization_required' : 'ready',
                ];
            })->values()->all(),
        ])->all();
    }

    /**
     * @param Collection<int, mixed> $notes
     * @return array<int, array<string, mixed>>
     */
    private function noteGroups(Collection $notes): array
    {
        return $notes
            ->groupBy(fn ($note): string => implode('|', [
                $note->section?->value ?? $note->section,
                $note->quotation_id ?? '',
                $note->vendor_id ?? '',
                $note->rfq_line_item_id ?? '',
            ]))
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'section' => $first->section?->value ?? $first->section,
                    'quotationId' => $first->quotation_id !== null ? (string) $first->quotation_id : null,
                    'vendorId' => $first->vendor_id !== null ? (string) $first->vendor_id : null,
                    'rfqLineItemId' => $first->rfq_line_item_id,
                    'notes' => QuotationComparisonNoteResource::collection($group->values())->resolve(),
                ];
            })
            ->values()
            ->all();
    }

    private function approvedNormalization(Quotation $quotation): ?QuotationNormalization
    {
        $normalization = $quotation->currentVersion?->currentNormalization;

        if (in_array($normalization?->status, [
            QuotationNormalizationStatus::Approved,
            QuotationNormalizationStatus::ApprovedWithWarnings,
        ], true)) {
            return $normalization;
        }

        return null;
    }

    /**
     * @param Collection<string, mixed> $fields
     */
    private function field(Collection $fields, string $path): ?string
    {
        $field = $fields->get($path);
        if ($field === null) {
            return null;
        }

        $value = $field->normalized_value;
        if (is_array($value)) {
            return $value['value'] ?? $value[0] ?? null;
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * @return array<string, int>
     */
    private function issueCounts(?QuotationNormalization $normalization): array
    {
        $issues = $normalization?->issues ?? collect();

        return [
            'blocking' => $issues->filter(fn ($issue): bool => ($issue->severity?->value ?? $issue->severity) === 'blocking')->count(),
            'warning' => $issues->filter(fn ($issue): bool => ($issue->severity?->value ?? $issue->severity) === 'warning')->count(),
            'info' => $issues->filter(fn ($issue): bool => ($issue->severity?->value ?? $issue->severity) === 'info')->count(),
        ];
    }
}
