<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\ApproveQuotationNormalization;
use Domains\Quotation\Actions\CreateQuotationNormalizationRevision;
use Domains\Quotation\Actions\RetryQuotationNormalization;
use Domains\Quotation\Actions\SaveQuotationNormalizationCorrections;
use Domains\Quotation\Actions\SaveQuotationNormalizationLineMappings;
use Domains\Quotation\Http\Requests\ApproveQuotationNormalizationRequest;
use Domains\Quotation\Http\Requests\ListQuotationNormalizationsRequest;
use Domains\Quotation\Http\Requests\SaveQuotationNormalizationCorrectionsRequest;
use Domains\Quotation\Http\Requests\SaveQuotationNormalizationLineMappingsRequest;
use Domains\Quotation\Http\Resources\QuotationNormalizationResource;
use Domains\Quotation\Http\Resources\QuotationNormalizationSummaryResource;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationNormalizationController extends Controller
{
    public function index(ListQuotationNormalizationsRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorize('viewAny', QuotationNormalization::class);
        $tenant = $this->tenantOrAbort($currentTenant);
        $statuses = $request->validated('status') ?? [
            QuotationNormalizationStatus::NeedsReview->value,
            QuotationNormalizationStatus::Failed->value,
            QuotationNormalizationStatus::ReadyForApproval->value,
        ];

        $normalizations = QuotationNormalization::query()
            ->with([
                'quotation.vendor',
                'quotationVersion.quotation.rfq',
                'issues',
            ])
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', $statuses)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => QuotationNormalizationSummaryResource::collection($normalizations)->resolve(),
        ]);
    }

    public function show(CurrentTenant $currentTenant, int $normalization): QuotationNormalizationResource
    {
        $model = $this->findTenantNormalization($this->tenantOrAbort($currentTenant), $normalization);
        $this->authorize('view', $model);

        return new QuotationNormalizationResource($model->load([
            'quotation.vendor',
            'quotation.rfq',
            'quotationVersion.quotation.rfq',
            'fields',
            'lineGroups.mappings',
            'attachments',
            'issues',
        ]));
    }

    public function corrections(SaveQuotationNormalizationCorrectionsRequest $request, CurrentTenant $currentTenant, int $normalization, SaveQuotationNormalizationCorrections $action): QuotationNormalizationResource
    {
        $model = $this->findTenantNormalization($this->tenantOrAbort($currentTenant), $normalization);
        $this->authorize('update', $model);

        return new QuotationNormalizationResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $model, $request->validated('corrections')));
    }

    public function lineMappings(SaveQuotationNormalizationLineMappingsRequest $request, CurrentTenant $currentTenant, int $normalization, SaveQuotationNormalizationLineMappings $action): QuotationNormalizationResource
    {
        $model = $this->findTenantNormalization($this->tenantOrAbort($currentTenant), $normalization);
        $this->authorize('update', $model);

        return new QuotationNormalizationResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $model, $request->validated('lineGroups')));
    }

    public function approve(ApproveQuotationNormalizationRequest $request, CurrentTenant $currentTenant, int $normalization, ApproveQuotationNormalization $action): QuotationNormalizationResource
    {
        $model = $this->findTenantNormalization($this->tenantOrAbort($currentTenant), $normalization);
        $this->authorize('approve', $model);

        return new QuotationNormalizationResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $model, $request->validated()));
    }

    public function approveWithWarnings(ApproveQuotationNormalizationRequest $request, CurrentTenant $currentTenant, int $normalization, ApproveQuotationNormalization $action): QuotationNormalizationResource
    {
        $model = $this->findTenantNormalization($this->tenantOrAbort($currentTenant), $normalization);
        $this->authorize('approveWithWarnings', $model);

        return new QuotationNormalizationResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $model, $request->validated(), true));
    }

    public function revision(Request $request, CurrentTenant $currentTenant, int $normalization, CreateQuotationNormalizationRevision $action): JsonResponse
    {
        $model = $this->findTenantNormalization($this->tenantOrAbort($currentTenant), $normalization);
        $this->authorize('createRevision', $model);

        return (new QuotationNormalizationResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $model)))
            ->response()
            ->setStatusCode(201);
    }

    private function findTenantNormalization(Tenant $tenant, int $id): QuotationNormalization
    {
        return QuotationNormalization::query()
            ->with(['quotation.vendor', 'quotationVersion.quotation.rfq', 'fields', 'lineGroups.mappings', 'attachments', 'issues'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
