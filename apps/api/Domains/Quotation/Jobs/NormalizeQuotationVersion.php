<?php

namespace Domains\Quotation\Jobs;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\RunDeterministicQuotationNormalizer;
use Domains\Quotation\Actions\StartQuotationNormalization;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class NormalizeQuotationVersion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $quotationVersionId,
    ) {
    }

    public function handle(
        StartQuotationNormalization $starter,
        RunDeterministicQuotationNormalizer $normalizer,
        AuditRecorder $auditRecorder,
        NotificationRecorder $notificationRecorder,
    ): void {
        $tenant = Tenant::query()->whereKey($this->tenantId)->firstOrFail();
        $version = QuotationVersion::query()
            ->with(['quotation', 'lineItems'])
            ->whereKey($this->quotationVersionId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $claimedNormalization = $this->claimNormalization($starter->handle($tenant, $version));

        if ($claimedNormalization === null) {
            return;
        }

        try {
            $normalized = $normalizer->handle($tenant, $version, $claimedNormalization);

            if ($normalized->status === QuotationNormalizationStatus::NeedsReview) {
                $this->notifyBestEffort(
                    $tenant,
                    $normalized,
                    $notificationRecorder,
                    NotificationPreferenceDefaults::EVENT_QUOTATION_NORMALIZATION_NEEDS_REVIEW,
                    'Quotation normalization needs review',
                    'The quotation normalization requires buyer review.',
                );
            }
        } catch (Throwable $throwable) {
            if ($claimedNormalization instanceof QuotationNormalization) {
                $claimedNormalization->forceFill([
                    'status' => QuotationNormalizationStatus::Failed,
                    'last_job_error' => $throwable->getMessage(),
                ])->save();

                $auditRecorder->record(new AuditEventData(
                    tenant: $claimedNormalization->tenant,
                    actor: null,
                    action: 'quotation_normalization.failed',
                    subject: $claimedNormalization,
                    metadata: [
                        'quotationId' => (string) $claimedNormalization->quotation_id,
                        'quotationVersionId' => (string) $claimedNormalization->quotation_version_id,
                        'normalizationId' => (string) $claimedNormalization->id,
                        'jobAttemptCount' => $claimedNormalization->job_attempt_count,
                        'error' => $throwable->getMessage(),
                    ],
                    subjectDisplay: $claimedNormalization->quotation?->number,
                ));

                $this->notifyBestEffort(
                    $claimedNormalization->tenant,
                    $claimedNormalization,
                    $notificationRecorder,
                    NotificationPreferenceDefaults::EVENT_QUOTATION_NORMALIZATION_FAILED,
                    'Quotation normalization failed',
                    $throwable->getMessage(),
                );
            }

            throw $throwable;
        }
    }

    private function claimNormalization(QuotationNormalization $normalization): ?QuotationNormalization
    {
        return DB::transaction(function () use ($normalization): ?QuotationNormalization {
            $claimed = QuotationNormalization::query()
                ->with(['quotation', 'quotationVersion'])
                ->whereKey($normalization->id)
                ->where('tenant_id', $normalization->tenant_id)
                ->lockForUpdate()
                ->first();

            if ($claimed === null) {
                return null;
            }

            if (in_array($claimed->status, [
                QuotationNormalizationStatus::ReadyForApproval,
                QuotationNormalizationStatus::NeedsReview,
                QuotationNormalizationStatus::Superseded,
            ], true)) {
                return null;
            }

            if ($claimed->status === QuotationNormalizationStatus::Processing && (int) $claimed->job_attempt_count > 0) {
                return null;
            }

            if (! in_array($claimed->status, [
                QuotationNormalizationStatus::Pending,
                QuotationNormalizationStatus::Processing,
                QuotationNormalizationStatus::Failed,
            ], true)) {
                return null;
            }

            $claimed->forceFill([
                'job_attempt_count' => ((int) $claimed->job_attempt_count) + 1,
            ])->save();

            return $claimed->refresh()->load(['quotation', 'quotationVersion']);
        });
    }

    private function notifyBestEffort(
        Tenant $tenant,
        QuotationNormalization $normalization,
        NotificationRecorder $notificationRecorder,
        string $eventType,
        string $title,
        string $body,
    ): void {
        try {
            $this->notifyReviewers(
                $tenant,
                $normalization,
                $notificationRecorder,
                $eventType,
                $title,
                $body,
            );
        } catch (Throwable $notificationThrowable) {
            report($notificationThrowable);
        }
    }

    private function notifyReviewers(
        Tenant $tenant,
        QuotationNormalization $normalization,
        NotificationRecorder $notificationRecorder,
        string $eventType,
        string $title,
        string $body,
    ): void {
        $recipients = $tenant->users()
            ->wherePivotIn('role', ['buyer', 'admin'])
            ->orderBy('users.id')
            ->get();

        $notificationRecorder->record($tenant, $recipients, new NotificationData(
            type: $eventType,
            title: $title,
            body: $body,
            subject: $normalization,
            subjectLabel: $normalization->quotation?->number,
            metadata: [
                'quotationId' => (string) $normalization->quotation_id,
                'quotationVersionId' => (string) $normalization->quotation_version_id,
                'normalizationId' => (string) $normalization->id,
                'status' => $normalization->status->value,
            ],
        ));
    }
}
