<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationNormalizationCorrection;
use Domains\Quotation\Models\QuotationNormalizationField;
use Domains\Quotation\Models\QuotationNormalizationIssue;
use Domains\Quotation\States\QuotationNormalizationIssueStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SaveQuotationNormalizationCorrections
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $corrections
     */
    public function handle(Tenant $tenant, ?User $actor, QuotationNormalization $normalization, array $corrections): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $actor, $normalization, $corrections): QuotationNormalization {
            $lockedNormalization = QuotationNormalization::query()
                ->with(['quotation', 'quotationVersion', 'issues', 'fields'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($normalization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedNormalization->isMutable()) {
                throw new ConflictHttpException('Quotation normalization is not mutable.');
            }

            foreach ($corrections as $correctionPayload) {
                $issue = null;
                if (! empty($correctionPayload['issueId'])) {
                    $issue = QuotationNormalizationIssue::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('normalization_id', $lockedNormalization->id)
                        ->whereKey($correctionPayload['issueId'])
                        ->lockForUpdate()
                        ->first();

                    if ($issue === null) {
                        throw new ConflictHttpException('Quotation normalization correction issue must belong to the same tenant and normalization.');
                    }
                }

                $field = QuotationNormalizationField::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('normalization_id', $lockedNormalization->id)
                    ->where('field_path', $correctionPayload['fieldPath'])
                    ->lockForUpdate()
                    ->first();

                QuotationNormalizationCorrection::query()->create([
                    'tenant_id' => $tenant->id,
                    'normalization_id' => $lockedNormalization->id,
                    'issue_id' => $issue?->id,
                    'field_path' => $correctionPayload['fieldPath'],
                    'original_raw_value' => $field?->raw_value,
                    'previous_normalized_value' => $field?->normalized_value,
                    'corrected_value' => $correctionPayload['correctedValue'],
                    'corrected_by_user_id' => $actor?->id,
                    'correction_note' => $correctionPayload['correctionNote'],
                ]);

                if ($field !== null) {
                    $field->forceFill([
                        'normalized_value' => $correctionPayload['correctedValue'],
                    ])->save();
                }

                if ($issue !== null) {
                    $issue->forceFill([
                        'status' => QuotationNormalizationIssueStatus::Resolved,
                        'resolved_by_user_id' => $actor?->id,
                        'resolved_at' => now(),
                        'resolution_note' => $correctionPayload['resolutionNote'] ?? $correctionPayload['correctionNote'],
                    ])->save();
                }
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_normalization.corrected',
                subject: $lockedNormalization,
                metadata: [
                    'normalizationId' => (string) $lockedNormalization->id,
                    'quotationId' => (string) $lockedNormalization->quotation_id,
                    'quotationVersionId' => (string) $lockedNormalization->quotation_version_id,
                    'correctionCount' => count($corrections),
                    'status' => $lockedNormalization->status->value,
                ],
                subjectDisplay: $lockedNormalization->quotation?->number,
            ));

            return $lockedNormalization->refresh()->load(['quotation', 'quotationVersion', 'fields', 'lineGroups.mappings', 'attachments', 'issues']);
        });
    }
}
