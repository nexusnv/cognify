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
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApproveQuotationNormalization
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Tenant $tenant, ?User $actor, QuotationNormalization $normalization, array $payload, bool $withWarnings = false): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $actor, $normalization, $payload, $withWarnings): QuotationNormalization {
            $lockedNormalization = QuotationNormalization::query()
                ->with(['quotation.vendor', 'quotationVersion', 'issues'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($normalization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedNormalization->isMutable()) {
                throw new ConflictHttpException('Quotation normalization is not mutable.');
            }

            if (! in_array($lockedNormalization->status, [
                QuotationNormalizationStatus::NeedsReview,
                QuotationNormalizationStatus::ReadyForApproval,
            ], true)) {
                throw new ConflictHttpException('Quotation normalization is not ready for approval.');
            }

            $hasBlockingIssues = $lockedNormalization->issues->contains(function ($issue): bool {
                $severity = $issue->severity instanceof QuotationNormalizationIssueSeverity
                    ? $issue->severity
                    : QuotationNormalizationIssueSeverity::from($issue->severity);

                return $severity === QuotationNormalizationIssueSeverity::Blocking && ($issue->status?->value ?? $issue->status) !== 'resolved';
            });

            if ($hasBlockingIssues) {
                throw new ConflictHttpException('Quotation normalization has unresolved blocking issues.');
            }

            $hasUnresolvedWarnings = $lockedNormalization->issues->contains(function ($issue): bool {
                $severity = $issue->severity instanceof QuotationNormalizationIssueSeverity
                    ? $issue->severity
                    : QuotationNormalizationIssueSeverity::from($issue->severity);

                return $severity === QuotationNormalizationIssueSeverity::Warning && ($issue->status?->value ?? $issue->status) !== 'resolved';
            });

            if ($withWarnings && ! $hasUnresolvedWarnings) {
                throw new ConflictHttpException('Quotation normalization has no unresolved warning issues.');
            }

            if (! $withWarnings && $hasUnresolvedWarnings) {
                throw new ConflictHttpException('Quotation normalization has unresolved warning issues; resolve warnings or approve with warnings.');
            }

            $status = $withWarnings ? QuotationNormalizationStatus::ApprovedWithWarnings : QuotationNormalizationStatus::Approved;
            $lockedNormalization->forceFill([
                'status' => $status,
                'approved_at' => now(),
                'approved_by_user_id' => $actor?->id,
                'approval_note' => $payload['approvalNote'] ?? null,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_normalization.approved',
                subject: $lockedNormalization,
                metadata: [
                    'normalizationId' => (string) $lockedNormalization->id,
                    'quotationId' => (string) $lockedNormalization->quotation_id,
                    'quotationVersionId' => (string) $lockedNormalization->quotation_version_id,
                    'status' => $lockedNormalization->status->value,
                    'approvalNote' => $lockedNormalization->approval_note,
                ],
                subjectDisplay: $lockedNormalization->quotation?->number,
            ));

            $this->notificationRecorder->record($tenant, $tenant->users()->wherePivotIn('role', ['buyer', 'admin'])->get(), new NotificationData(
                type: NotificationPreferenceDefaults::EVENT_QUOTATION_NORMALIZATION_APPROVED,
                title: 'Quotation normalization approved',
                body: 'The quotation normalization was approved.',
                subject: $lockedNormalization,
                subjectLabel: $lockedNormalization->quotation?->number,
                metadata: [
                    'normalizationId' => (string) $lockedNormalization->id,
                    'quotationId' => (string) $lockedNormalization->quotation_id,
                    'quotationVersionId' => (string) $lockedNormalization->quotation_version_id,
                    'status' => $lockedNormalization->status->value,
                ],
            ));

            return $lockedNormalization->refresh()->load(['quotation', 'quotationVersion', 'fields', 'lineGroups.mappings', 'attachments', 'issues']);
        });
    }
}
