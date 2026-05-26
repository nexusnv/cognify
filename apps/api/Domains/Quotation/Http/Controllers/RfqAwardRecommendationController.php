<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\RouteSubjectForApproval;
use Domains\Approval\Http\Resources\ApprovalPreviewResource;
use Domains\Approval\Http\Resources\ApprovalSummaryResource;
use Domains\Quotation\Actions\BuildRfqAwardApprovalPreview;
use Domains\Quotation\Actions\BuildRfqAwardApprovalSummary;
use Domains\Quotation\Actions\BuildRfqAwardRecommendationContext;
use Domains\Quotation\Actions\SaveRfqAwardRecommendation;
use Domains\Quotation\Actions\SubmitRfqAwardRecommendation;
use Domains\Quotation\Actions\WithdrawRfqAwardRecommendation;
use Domains\Quotation\Http\Requests\SaveRfqAwardRecommendationRequest;
use Domains\Quotation\Http\Requests\WithdrawRfqAwardRecommendationRequest;
use Domains\Quotation\Http\Resources\RfqAwardRecommendationContextResource;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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

    public function routeApproval(
        CurrentTenant $currentTenant,
        int $rfq,
        RouteSubjectForApproval $routeSubjectForApproval,
        BuildRfqAwardApprovalSummary $summaryBuilder,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $recommendation = $this->findTenantRecommendationForRfq($tenant, $model);

        $this->authorize('manage', $recommendation);

        if (! $recommendation->statusState()->isPendingApproval()) {
            $existing = $summaryBuilder->handle($tenant, $recommendation);
            if ($existing !== null && $existing->status->value === 'active') {
                return response()->json([
                    'data' => (new ApprovalSummaryResource($existing))->resolve(),
                ]);
            }

            throw new ConflictHttpException('Only pending award recommendations can be routed for approval.');
        }

        $instance = $routeSubjectForApproval->handle($tenant, request()->user(), $recommendation);
        $instance->load(['stages', 'tasks.assignee', 'tasks.decidedBy']);

        return response()->json([
            'data' => (new ApprovalSummaryResource($instance))->resolve(),
        ]);
    }

    public function approvalSummary(
        CurrentTenant $currentTenant,
        int $rfq,
        BuildRfqAwardApprovalSummary $summaryBuilder,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $recommendation = $this->findTenantRecommendationForRfq($tenant, $model);

        $this->authorize('viewApproval', $recommendation);

        $instance = $summaryBuilder->handle($tenant, $recommendation);

        return response()->json([
            'data' => $instance !== null ? (new ApprovalSummaryResource($instance))->resolve() : null,
        ]);
    }

    public function approvalPreview(
        CurrentTenant $currentTenant,
        int $rfq,
        BuildRfqAwardApprovalPreview $previewBuilder,
    ): ApprovalPreviewResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $recommendation = $this->findTenantRecommendationForRfq($tenant, $model);

        $this->authorize('viewApproval', $recommendation);

        return new ApprovalPreviewResource($previewBuilder->handle($tenant, request()->user(), $recommendation));
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantRecommendationForRfq(Tenant $tenant, Rfq $rfq): RfqAwardRecommendation
    {
        return RfqAwardRecommendation::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->latest('updated_at')
            ->latest('id')
            ->firstOrFail();
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
