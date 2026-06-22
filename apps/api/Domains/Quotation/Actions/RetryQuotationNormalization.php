<?php

namespace Domains\Quotation\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Jobs\NormalizeQuotationVersion;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RetryQuotationNormalization
{
    public function handle(Tenant $tenant, ?User $actor, QuotationVersion $version): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $version): QuotationNormalization {
            $lockedVersion = QuotationVersion::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            $normalization = QuotationNormalization::query()
                ->with(['quotation', 'quotationVersion', 'fields', 'lineGroups.mappings', 'attachments', 'issues'])
                ->where('tenant_id', $tenant->id)
                ->where('quotation_version_id', $lockedVersion->id)
                ->where('status', QuotationNormalizationStatus::Failed)
                ->orderByDesc('normalization_revision')
                ->lockForUpdate()
                ->first();

            if ($normalization === null) {
                throw new ConflictHttpException('Quotation normalization retry is only available for failed normalizations.');
            }

            $normalization->forceFill([
                'status' => QuotationNormalizationStatus::Processing,
                'job_attempt_count' => 0,
                'last_job_error' => null,
            ])->save();

            NormalizeQuotationVersion::dispatch($tenant->id, $lockedVersion->id)->afterCommit();

            return $normalization->refresh()->load(['quotation', 'quotationVersion', 'fields', 'lineGroups.mappings', 'attachments', 'issues']);
        });
    }
}
