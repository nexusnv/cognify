<?php

namespace Domains\Quotation\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Jobs\NormalizeQuotationVersion;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RetryQuotationNormalization
{
    public function handle(Tenant $tenant, ?User $actor, QuotationVersion $version): QuotationNormalization
    {
        return DB::transaction(function () use ($tenant, $actor, $version): QuotationNormalization {
            $lockedVersion = QuotationVersion::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            NormalizeQuotationVersion::dispatch($tenant->id, $lockedVersion->id)->afterCommit();

            $normalization = QuotationNormalization::query()
                ->where('tenant_id', $tenant->id)
                ->where('quotation_version_id', $lockedVersion->id)
                ->orderByDesc('normalization_revision')
                ->first();

            if ($normalization === null) {
                throw new InvalidArgumentException('Quotation normalization could not be queued.');
            }

            return $normalization->refresh()->load(['quotation', 'quotationVersion', 'fields', 'lineGroups.mappings', 'attachments', 'issues']);
        });
    }
}
