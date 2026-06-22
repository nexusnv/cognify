<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationNormalizationAttachment;
use Domains\Quotation\Models\QuotationNormalizationField;
use Domains\Quotation\Models\QuotationNormalizationIssue;
use Domains\Quotation\Models\QuotationNormalizationLineGroup;
use Domains\Quotation\Models\QuotationNormalizationLineMapping;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateQuotationNormalizationRevision
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(Tenant $tenant, ?User $actor, QuotationNormalization $normalization): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $actor, $normalization): QuotationNormalization {
            $current = QuotationNormalization::query()
                ->with(['quotation', 'quotationVersion', 'fields', 'attachments', 'issues', 'lineGroups.mappings'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($normalization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($current->status, [
                QuotationNormalizationStatus::Approved,
                QuotationNormalizationStatus::ApprovedWithWarnings,
            ], true)) {
                throw new ConflictHttpException('Quotation normalization revision can only be created from an approved normalization.');
            }

            $currentRevisionExists = QuotationNormalization::query()
                ->where('tenant_id', $tenant->id)
                ->where('quotation_version_id', $current->quotation_version_id)
                ->where('is_current_for_version', true)
                ->where('id', '!=', $current->id)
                ->lockForUpdate()
                ->get()
                ->isNotEmpty();

            if ($currentRevisionExists) {
                throw new ConflictHttpException('A current quotation normalization revision already exists for this version.');
            }

            $current->forceFill([
                'is_current_for_version' => false,
                'superseded_at' => now(),
            ])->save();

            $nextRevision = ((int) QuotationNormalization::query()
                ->where('tenant_id', $tenant->id)
                ->where('quotation_version_id', $current->quotation_version_id)
                ->max('normalization_revision')) + 1;

            $revision = QuotationNormalization::query()->create([
                'tenant_id' => $tenant->id,
                'quotation_id' => $current->quotation_id,
                'quotation_version_id' => $current->quotation_version_id,
                'normalization_revision' => $nextRevision,
                'status' => QuotationNormalizationStatus::NeedsReview,
                'is_current_for_version' => true,
                'algorithm_version' => $current->algorithm_version,
                'metadata' => $current->metadata,
            ]);

            $current->fields->each(function (QuotationNormalizationField $field) use ($tenant, $revision): void {
                QuotationNormalizationField::query()->create([
                    'tenant_id' => $tenant->id,
                    'normalization_id' => $revision->id,
                    'field_path' => $field->field_path,
                    'raw_value' => $field->raw_value,
                    'normalized_value' => $field->normalized_value,
                    'data_type' => $field->data_type,
                    'currency' => $field->currency,
                    'confidence' => $field->confidence,
                    'source' => $field->source,
                    'provenance' => $field->provenance,
                ]);
            });

            $groupIdMap = [];
            $current->lineGroups->each(function (QuotationNormalizationLineGroup $group) use ($tenant, $revision, &$groupIdMap): void {
                $newGroup = QuotationNormalizationLineGroup::query()->create([
                    'tenant_id' => $tenant->id,
                    'normalization_id' => $revision->id,
                    'group_number' => $group->group_number,
                    'pricing_mode' => $group->pricing_mode,
                    'description' => $group->description,
                    'currency' => $group->currency,
                    'bundle_total_amount' => $group->bundle_total_amount,
                    'notes' => $group->notes,
                ]);

                $groupIdMap[(string) $group->id] = $newGroup->id;
            });

            $current->lineGroups->each(function (QuotationNormalizationLineGroup $group) use ($tenant, $groupIdMap): void {
                $newGroupId = $groupIdMap[(string) $group->id];
                $newGroup = QuotationNormalizationLineGroup::query()->whereKey($newGroupId)->firstOrFail();

                $group->mappings->each(function (QuotationNormalizationLineMapping $mapping) use ($tenant, $newGroup): void {
                    QuotationNormalizationLineMapping::query()->create([
                        'tenant_id' => $tenant->id,
                        'quotation_normalization_line_group_id' => $newGroup->id,
                        'rfq_line_item_id' => $mapping->rfq_line_item_id,
                        'quotation_version_line_item_id' => $mapping->quotation_version_line_item_id,
                        'mapping_type' => $mapping->mapping_type,
                        'quantity' => $mapping->quantity,
                        'unit' => $mapping->unit,
                        'unit_price' => $mapping->unit_price,
                        'line_total' => $mapping->line_total,
                        'buyer_note' => $mapping->buyer_note,
                    ]);
                });
            });

            $current->attachments->each(function (QuotationNormalizationAttachment $attachment) use ($tenant, $revision): void {
                QuotationNormalizationAttachment::query()->create([
                    'tenant_id' => $tenant->id,
                    'normalization_id' => $revision->id,
                    'quotation_version_attachment_id' => $attachment->quotation_version_attachment_id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'extension' => $attachment->extension,
                    'size_bytes' => $attachment->size_bytes,
                    'checksum_sha256' => $attachment->checksum_sha256,
                    'available' => $attachment->available,
                    'source' => $attachment->source,
                    'uploaded_at' => $attachment->uploaded_at,
                    'evidence_role' => $attachment->evidence_role,
                    'issue_summary' => $attachment->issue_summary,
                ]);
            });

            $current->issues->each(function (QuotationNormalizationIssue $issue) use ($tenant, $revision): void {
                QuotationNormalizationIssue::query()->create([
                    'tenant_id' => $tenant->id,
                    'normalization_id' => $revision->id,
                    'severity' => $issue->severity,
                    'field_path' => $issue->field_path,
                    'issue_code' => $issue->issue_code,
                    'message' => $issue->message,
                    'raw_value' => $issue->raw_value,
                    'suggested_value' => $issue->suggested_value,
                    'status' => $issue->status,
                    'resolved_by_user_id' => $issue->resolved_by_user_id,
                    'resolved_at' => $issue->resolved_at,
                    'resolution_note' => $issue->resolution_note,
                ]);
            });

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_normalization.revision_created',
                subject: $revision,
                metadata: [
                    'normalizationId' => (string) $revision->id,
                    'quotationId' => (string) $revision->quotation_id,
                    'quotationVersionId' => (string) $revision->quotation_version_id,
                    'normalizationRevision' => $revision->normalization_revision,
                    'previousNormalizationId' => (string) $current->id,
                ],
                subjectDisplay: $revision->quotation?->number,
            ));

            return $revision->refresh()->load(['quotation', 'quotationVersion', 'fields', 'lineGroups.mappings', 'attachments', 'issues']);
        });
    }
}
