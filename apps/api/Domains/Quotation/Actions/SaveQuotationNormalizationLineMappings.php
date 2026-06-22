<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationNormalizationIssue;
use Domains\Quotation\Models\QuotationNormalizationLineGroup;
use Domains\Quotation\Models\QuotationNormalizationLineMapping;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationIssueStatus;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\Support\QuotationNormalizationIssueCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SaveQuotationNormalizationLineMappings
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lineGroups
     */
    public function handle(Tenant $tenant, ?User $actor, QuotationNormalization $normalization, array $lineGroups): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $actor, $normalization, $lineGroups): QuotationNormalization {
            $lockedNormalization = QuotationNormalization::query()
                ->with(['quotation', 'quotationVersion.lineItems', 'quotationVersion.quotation.rfq', 'issues'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($normalization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedNormalization->isMutable()) {
                throw new ConflictHttpException('Quotation normalization is not mutable.');
            }

            $allowedRfQLineItemIds = collect($lockedNormalization->quotationVersion?->quotation?->rfq?->line_items ?? [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->all();
            $allowedQuotationVersionLineItemIds = collect($lockedNormalization->quotationVersion?->lineItems ?? [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->all();

            $lockedNormalization->lineGroups()->with('mappings')->get()->each(function (QuotationNormalizationLineGroup $group): void {
                $group->mappings()->delete();
                $group->delete();
            });

            foreach ($lineGroups as $groupIndex => $groupPayload) {
                $group = QuotationNormalizationLineGroup::query()->create([
                    'tenant_id' => $tenant->id,
                    'normalization_id' => $lockedNormalization->id,
                    'group_number' => $groupPayload['groupNumber'],
                    'pricing_mode' => $groupPayload['pricingMode'],
                    'description' => $groupPayload['description'] ?? null,
                    'currency' => $groupPayload['currency'] ?? null,
                    'bundle_total_amount' => $groupPayload['bundleTotalAmount'] ?? null,
                    'notes' => $groupPayload['notes'] ?? null,
                ]);

                foreach ($groupPayload['mappings'] as $mappingIndex => $mappingPayload) {
                    if (($mappingPayload['rfqLineItemId'] ?? null) !== null && ! in_array((string) $mappingPayload['rfqLineItemId'], $allowedRfQLineItemIds, true)) {
                        throw ValidationException::withMessages([
                            "lineGroups.{$groupIndex}.mappings.{$mappingIndex}.rfqLineItemId" => ['The selected RFQ line item is invalid for this normalization.'],
                        ]);
                    }

                    if (($mappingPayload['quotationVersionLineItemId'] ?? null) !== null && ! in_array((string) $mappingPayload['quotationVersionLineItemId'], $allowedQuotationVersionLineItemIds, true)) {
                        throw ValidationException::withMessages([
                            "lineGroups.{$groupIndex}.mappings.{$mappingIndex}.quotationVersionLineItemId" => ['The selected quotation version line item is invalid for this normalization.'],
                        ]);
                    }

                    QuotationNormalizationLineMapping::query()->create([
                        'tenant_id' => $tenant->id,
                        'quotation_normalization_line_group_id' => $group->id,
                        'rfq_line_item_id' => $mappingPayload['rfqLineItemId'] ?? null,
                        'quotation_version_line_item_id' => $mappingPayload['quotationVersionLineItemId'] ?? null,
                        'mapping_type' => $mappingPayload['mappingType'],
                        'quantity' => $mappingPayload['quantity'] ?? null,
                        'unit' => $mappingPayload['unit'] ?? null,
                        'unit_price' => $mappingPayload['unitPrice'] ?? null,
                        'line_total' => $mappingPayload['lineTotal'] ?? null,
                        'buyer_note' => $mappingPayload['buyerNote'] ?? null,
                    ]);
                }
            }

            $this->resolveMappingIssues($lockedNormalization);
            $this->recalculateStatus($lockedNormalization);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_normalization.line_mappings_saved',
                subject: $lockedNormalization,
                metadata: [
                    'normalizationId' => (string) $lockedNormalization->id,
                    'quotationId' => (string) $lockedNormalization->quotation_id,
                    'quotationVersionId' => (string) $lockedNormalization->quotation_version_id,
                    'lineGroupCount' => count($lineGroups),
                    'status' => $lockedNormalization->status->value,
                ],
                subjectDisplay: $lockedNormalization->quotation?->number,
            ));

            return $lockedNormalization->refresh()->load(['quotation', 'quotationVersion', 'fields', 'lineGroups.mappings', 'attachments', 'issues']);
        });
    }

    private function resolveMappingIssues(QuotationNormalization $normalization): void
    {
        $requiredLineIds = collect($normalization->quotationVersion?->quotation?->rfq?->line_items ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values();

        $mappedLineIds = $normalization->lineGroups()
            ->with('mappings')
            ->get()
            ->flatMap(fn (QuotationNormalizationLineGroup $group) => $group->mappings->pluck('rfq_line_item_id'))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($requiredLineIds->diff($mappedLineIds)->isEmpty()) {
            $normalization->issues()
                ->where('issue_code', QuotationNormalizationIssueCatalog::REQUIRED_RFQ_LINE_UNMAPPED)
                ->where('status', '!=', QuotationNormalizationIssueStatus::Resolved->value)
                ->get()
                ->each(function (QuotationNormalizationIssue $issue): void {
                    $issue->forceFill([
                        'status' => QuotationNormalizationIssueStatus::Resolved,
                        'resolved_at' => now(),
                    ])->save();
                });
        } else {
            $issues = $normalization->issues()
                ->where('issue_code', QuotationNormalizationIssueCatalog::REQUIRED_RFQ_LINE_UNMAPPED)
                ->get();

            if ($issues->isEmpty()) {
                $normalization->issues()->create([
                    'tenant_id' => $normalization->tenant_id,
                    'severity' => QuotationNormalizationIssueSeverity::Blocking,
                    'status' => QuotationNormalizationIssueStatus::Open,
                    'issue_code' => QuotationNormalizationIssueCatalog::REQUIRED_RFQ_LINE_UNMAPPED,
                    'field_path' => 'lineGroups',
                    'message' => 'Required RFQ line items must be mapped before approval.',
                ]);

                return;
            }

            $issues->each(function (QuotationNormalizationIssue $issue): void {
                $issue->forceFill([
                    'severity' => QuotationNormalizationIssueSeverity::Blocking,
                    'status' => QuotationNormalizationIssueStatus::Open,
                    'resolved_by_user_id' => null,
                    'resolved_at' => null,
                    'resolution_note' => null,
                ])->save();
            });
        }
    }

    private function recalculateStatus(QuotationNormalization $normalization): void
    {
        $issues = $normalization->issues()->get();
        $hasBlockingIssues = $issues->contains(fn (QuotationNormalizationIssue $issue): bool => $issue->severity->value === 'blocking' && $issue->status->value !== 'resolved');
        $nextStatus = $hasBlockingIssues ? QuotationNormalizationStatus::NeedsReview : QuotationNormalizationStatus::ReadyForApproval;

        $normalization->forceFill([
            'status' => $nextStatus,
        ])->save();
    }
}
