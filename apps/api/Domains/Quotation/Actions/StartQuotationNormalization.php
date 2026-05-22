<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartQuotationNormalization
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    public function handle(Tenant $tenant, QuotationVersion $version, ?User $actor = null): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $version, $actor): QuotationNormalization {
            $lockedVersion = QuotationVersion::query()
                ->with('quotation')
                ->whereKey($version->id)
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quotation = Quotation::query()
                ->whereKey($lockedVersion->quotation_id)
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedVersion->tenant_id !== (int) $quotation->tenant_id) {
                throw new InvalidArgumentException('Quotation normalization version must belong to the same tenant and quotation.');
            }

            $existingNormalization = QuotationNormalization::query()
                ->where('tenant_id', $tenant->id)
                ->where('quotation_version_id', $lockedVersion->id)
                ->whereIn('status', [
                    QuotationNormalizationStatus::Pending->value,
                    QuotationNormalizationStatus::Processing->value,
                    QuotationNormalizationStatus::NeedsReview->value,
                    QuotationNormalizationStatus::ReadyForApproval->value,
                    QuotationNormalizationStatus::Failed->value,
                ])
                ->lockForUpdate()
                ->orderByDesc('normalization_revision')
                ->first();

            if ($existingNormalization !== null) {
                return $existingNormalization->refresh()->load(['quotationVersion', 'quotation']);
            }

            QuotationNormalization::query()
                ->where('tenant_id', $tenant->id)
                ->where('quotation_id', $quotation->id)
                ->where('quotation_version_id', '!=', $lockedVersion->id)
                ->whereIn('status', [
                    QuotationNormalizationStatus::Pending->value,
                    QuotationNormalizationStatus::Processing->value,
                    QuotationNormalizationStatus::NeedsReview->value,
                    QuotationNormalizationStatus::ReadyForApproval->value,
                    QuotationNormalizationStatus::Failed->value,
                ])
                ->lockForUpdate()
                ->get()
                ->each(function (QuotationNormalization $existing): void {
                    $existing->forceFill([
                        'status' => QuotationNormalizationStatus::Superseded,
                        'is_current_for_version' => false,
                        'superseded_at' => now(),
                    ])->save();
                });

            $nextRevision = ((int) QuotationNormalization::query()
                ->where('tenant_id', $tenant->id)
                ->where('quotation_version_id', $lockedVersion->id)
                ->max('normalization_revision')) + 1;

            $normalization = QuotationNormalization::query()->create([
                'tenant_id' => $tenant->id,
                'quotation_id' => $quotation->id,
                'quotation_version_id' => $lockedVersion->id,
                'normalization_revision' => $nextRevision,
                'status' => QuotationNormalizationStatus::Pending,
                'is_current_for_version' => true,
                'algorithm_version' => 'deterministic-v1',
            ]);

            $normalization->forceFill([
                'status' => QuotationNormalizationStatus::Processing,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_normalization.started',
                subject: $normalization,
                metadata: [
                    'quotationId' => (string) $quotation->id,
                    'quotationVersionId' => (string) $lockedVersion->id,
                    'normalizationId' => (string) $normalization->id,
                    'normalizationRevision' => $normalization->normalization_revision,
                    'algorithmVersion' => $normalization->algorithm_version,
                    'status' => $normalization->status->value,
                ],
                subjectDisplay: $quotation->number,
            ));

            return $normalization->refresh()->load(['quotationVersion', 'quotation']);
        });
    }
}
