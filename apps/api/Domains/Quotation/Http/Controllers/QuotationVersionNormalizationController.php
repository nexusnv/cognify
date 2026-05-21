<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\RetryQuotationNormalization;
use Domains\Quotation\Http\Resources\QuotationNormalizationResource;
use Domains\Quotation\Models\QuotationNormalization;
use Illuminate\Http\Request;

class QuotationVersionNormalizationController extends Controller
{
    public function retry(Request $request, CurrentTenant $currentTenant, int $version, RetryQuotationNormalization $action): QuotationNormalizationResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $normalization = $this->findTenantVersionNormalization($tenant, $version);
        $this->authorize('retry', $normalization);

        return new QuotationNormalizationResource($action->handle($tenant, $request->user(), $normalization->quotationVersion));
    }

    private function findTenantVersionNormalization(Tenant $tenant, int $version): QuotationNormalization
    {
        return QuotationNormalization::query()
            ->with(['quotation.vendor', 'quotationVersion.quotation.rfq', 'fields', 'lineGroups.mappings', 'attachments', 'issues'])
            ->where('tenant_id', $tenant->id)
            ->where('quotation_version_id', $version)
            ->where('status', 'failed')
            ->latest('normalization_revision')
            ->firstOrFail();
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
