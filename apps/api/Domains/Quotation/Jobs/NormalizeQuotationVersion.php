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
        $normalization = null;

        try {
            $tenant = Tenant::query()->whereKey($this->tenantId)->firstOrFail();
            $version = QuotationVersion::query()
                ->with(['quotation', 'lineItems'])
                ->whereKey($this->quotationVersionId)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $normalization = $starter->handle($tenant, $version);
            $normalization->forceFill([
                'job_attempt_count' => ((int) $normalization->job_attempt_count) + 1,
            ])->save();

            if (in_array($normalization->status, [
                QuotationNormalizationStatus::Pending,
                QuotationNormalizationStatus::Processing,
                QuotationNormalizationStatus::Failed,
            ], true)) {
                $normalized = $normalizer->handle($tenant, $version, $normalization);

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
            }
        } catch (Throwable $throwable) {
            if ($normalization instanceof QuotationNormalization) {
                $normalization->forceFill([
                    'status' => QuotationNormalizationStatus::Failed,
                    'last_job_error' => $throwable->getMessage(),
                ])->save();

                $auditRecorder->record(new AuditEventData(
                    tenant: $normalization->tenant,
                    actor: null,
                    action: 'quotation_normalization.failed',
                    subject: $normalization,
                    metadata: [
                        'quotationId' => (string) $normalization->quotation_id,
                        'quotationVersionId' => (string) $normalization->quotation_version_id,
                        'normalizationId' => (string) $normalization->id,
                        'jobAttemptCount' => $normalization->job_attempt_count,
                        'error' => $throwable->getMessage(),
                    ],
                    subjectDisplay: $normalization->quotation?->number,
                ));

                $this->notifyBestEffort(
                    $normalization->tenant,
                    $normalization,
                    $notificationRecorder,
                    NotificationPreferenceDefaults::EVENT_QUOTATION_NORMALIZATION_FAILED,
                    'Quotation normalization failed',
                    $throwable->getMessage(),
                );
            }

            throw $throwable;
        }
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
