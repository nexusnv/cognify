<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\BuildRfqAwardRecommendationContext;
use Domains\Quotation\Actions\SaveRfqAwardRecommendation;
use Domains\Quotation\Actions\SubmitRfqAwardRecommendation;
use Domains\Quotation\Actions\WithdrawRfqAwardRecommendation;
use Domains\Quotation\Http\Requests\SaveRfqAwardRecommendationRequest;
use Domains\Quotation\Http\Requests\WithdrawRfqAwardRecommendationRequest;
use Domains\Quotation\Http\Resources\RfqAwardRecommendationContextResource;
use Domains\Quotation\Models\Rfq;

class RfqAwardRecommendationController extends Controller
{
    public function show(
        CurrentTenant $currentTenant,
        int $rfq,
        BuildRfqAwardRecommendationContext $contextBuilder,
    ): RfqAwardRecommendationContextResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);

        return new RfqAwardRecommendationContextResource($contextBuilder->handle($tenant, request()->user(), $model));
    }

    public function save(
        SaveRfqAwardRecommendationRequest $request,
        CurrentTenant $currentTenant,
        int $rfq,
        SaveRfqAwardRecommendation $action,
        BuildRfqAwardRecommendationContext $contextBuilder,
    ): RfqAwardRecommendationContextResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $action->handle($tenant, $request->user(), $model, $request->validated());

        return new RfqAwardRecommendationContextResource($contextBuilder->handle($tenant, $request->user(), $model));
    }

    public function submit(
        SaveRfqAwardRecommendationRequest $request,
        CurrentTenant $currentTenant,
        int $rfq,
        SubmitRfqAwardRecommendation $action,
        BuildRfqAwardRecommendationContext $contextBuilder,
    ): RfqAwardRecommendationContextResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $action->handle($tenant, $request->user(), $model, $this->submitPayload($request));

        return new RfqAwardRecommendationContextResource($contextBuilder->handle($tenant, $request->user(), $model));
    }

    public function withdraw(
        WithdrawRfqAwardRecommendationRequest $request,
        CurrentTenant $currentTenant,
        int $rfq,
        WithdrawRfqAwardRecommendation $action,
        BuildRfqAwardRecommendationContext $contextBuilder,
    ): RfqAwardRecommendationContextResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $action->handle($tenant, $request->user(), $model, $request->validated());

        return new RfqAwardRecommendationContextResource($contextBuilder->handle($tenant, $request->user(), $model));
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function submitPayload(SaveRfqAwardRecommendationRequest $request): ?array
    {
        $payload = $request->validated();

        return $payload === [] ? null : $payload;
    }
}
