<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\AmbiguousTenantException;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\BuildQuotationComparison;
use Domains\Quotation\Actions\CompleteRfqScorecard;
use Domains\Quotation\Actions\CreateRfqScorecard;
use Domains\Quotation\Actions\ReopenRfqScorecard;
use Domains\Quotation\Actions\UpdateRfqScorecardScores;
use Domains\Quotation\Http\Requests\CreateRfqScorecardRequest;
use Domains\Quotation\Http\Requests\UpdateRfqScorecardScoresRequest;
use Domains\Quotation\Http\Resources\RfqScorecardResource;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqScorecard;
use Illuminate\Http\JsonResponse;

class RfqScorecardController extends Controller
{
    public function show(CurrentTenant $currentTenant, int $rfq, BuildQuotationComparison $comparison): RfqScorecardResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('create', [RfqScorecard::class, $model]);
        $scorecard = $this->findTenantScorecard($tenant, $model);
        $this->authorize('view', $scorecard);

        return new RfqScorecardResource($scorecard, $comparison->handle($tenant, $model));
    }

    public function store(
        CreateRfqScorecardRequest $request,
        CurrentTenant $currentTenant,
        int $rfq,
        CreateRfqScorecard $action,
        BuildQuotationComparison $comparison,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('create', [RfqScorecard::class, $model]);
        $template = $this->findTenantTemplate($tenant, (string) $request->validated('templateId'));
        $scorecard = $action->handle($tenant, $request->user(), $model, $template);

        return (new RfqScorecardResource(
            $scorecard->loadMissing('rfq'),
            $comparison->handle($tenant, $model),
        ))->response()->setStatusCode(200);
    }

    public function updateScores(
        UpdateRfqScorecardScoresRequest $request,
        CurrentTenant $currentTenant,
        int $rfq,
        UpdateRfqScorecardScores $action,
        BuildQuotationComparison $comparison,
    ): RfqScorecardResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $scorecard = $this->findTenantScorecard($tenant, $model);
        $this->authorize('update', $scorecard);
        $updated = $action->handle($tenant, $request->user(), $model, $scorecard, $request->validated());

        return new RfqScorecardResource($updated->loadMissing('rfq'), $comparison->handle($tenant, $model));
    }

    public function complete(
        CurrentTenant $currentTenant,
        int $rfq,
        CompleteRfqScorecard $action,
        BuildQuotationComparison $comparison,
    ): RfqScorecardResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $scorecard = $this->findTenantScorecard($tenant, $model);
        $this->authorize('complete', $scorecard);
        $completed = $action->handle($tenant, request()->user(), $model, $scorecard);

        return new RfqScorecardResource($completed->loadMissing('rfq'), $comparison->handle($tenant, $model));
    }

    public function reopen(
        CurrentTenant $currentTenant,
        int $rfq,
        ReopenRfqScorecard $action,
        BuildQuotationComparison $comparison,
    ): RfqScorecardResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $scorecard = $this->findTenantScorecard($tenant, $model);
        $this->authorize('reopen', $scorecard);
        $reopened = $action->handle($tenant, request()->user(), $model, $scorecard);

        return new RfqScorecardResource($reopened->loadMissing('rfq'), $comparison->handle($tenant, $model));
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->with(['requisition.requester', 'project'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantScorecard(Tenant $tenant, Rfq $rfq): RfqScorecard
    {
        return RfqScorecard::query()
            ->with(['rfq.requisition.requester', 'rfq.project', 'criteria', 'entries'])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->firstOrFail();
    }

    private function findTenantTemplate(Tenant $tenant, string $id): QuotationScoringTemplate
    {
        return QuotationScoringTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $user = request()->user();
        $tenantId = request()->header('X-Tenant-Id');

        if ($user !== null && ($tenantId === null || $tenantId === '')) {
            throw new AmbiguousTenantException('X-Tenant-Id header is required for quotation scoring routes.');
        }

        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
