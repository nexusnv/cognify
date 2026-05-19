<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\ClaimSourcingIntakeReview;
use Domains\Quotation\Actions\CloseSourcingIntakeReview;
use Domains\Quotation\Actions\CreateOrRevealSourcingIntakeReview;
use Domains\Quotation\Actions\ReassignSourcingIntakeReview;
use Domains\Quotation\Actions\RecordSourcingIntakeDecision;
use Domains\Quotation\Actions\UpdateSourcingIntakeReview;
use Domains\Quotation\Http\Requests\CloseSourcingIntakeReviewRequest;
use Domains\Quotation\Http\Requests\ListSourcingIntakeReviewsRequest;
use Domains\Quotation\Http\Requests\ReassignSourcingIntakeReviewRequest;
use Domains\Quotation\Http\Requests\RecordSourcingIntakeDecisionRequest;
use Domains\Quotation\Http\Requests\UpdateSourcingIntakeReviewRequest;
use Domains\Quotation\Http\Resources\SourcingIntakeReviewResource;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourcingIntakeReviewController extends Controller
{
    public function index(ListSourcingIntakeReviewsRequest $request, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorize('viewAny', SourcingIntakeReview::class);
        $tenant = $this->tenantOrAbort($currentTenant);
        $validated = $request->validated();

        $query = SourcingIntakeReview::query()
            ->with(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems'])
            ->where('tenant_id', $tenant->id);

        match ($validated['preset'] ?? null) {
            'unassigned' => $query->whereNull('assigned_buyer_id'),
            'mine' => $query->where('assigned_buyer_id', $request->user()->id),
            'needs_clarification' => $query->where('status', SourcingIntakeStatus::ClarificationRequested),
            'ready_for_rfq' => $query->where('status', SourcingIntakeStatus::ReadyForRfq),
            'closed' => $query->whereIn('status', [SourcingIntakeStatus::Closed, SourcingIntakeStatus::DirectAwardRecorded]),
            default => null,
        };

        $query
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['assignedBuyer'] ?? null, fn ($query, string $buyer) => $query->where('assigned_buyer_id', $buyer))
            ->when($validated['department'] ?? null, fn ($query, string $department) => $query->whereHas('requisition', fn ($subQuery) => $subQuery->where('department', $department)))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('requisition', function ($subQuery) use ($search): void {
                    $subQuery->where('number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            });

        match ($validated['sort'] ?? 'updated_desc') {
            'target_date_asc' => $query->orderBy('target_decision_date')->orderBy('id'),
            'needed_by_asc' => $query->join('requisitions', 'requisitions.id', '=', 'sourcing_intake_reviews.requisition_id')
                ->orderBy('requisitions.needed_by_date')
                ->orderBy('sourcing_intake_reviews.id')
                ->select('sourcing_intake_reviews.*'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $reviews = $query->paginate($request->integer('perPage', 20));

        return response()->json([
            'data' => SourcingIntakeReviewResource::collection($reviews->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $reviews->currentPage(),
                'perPage' => $reviews->perPage(),
                'total' => $reviews->total(),
                'lastPage' => $reviews->lastPage(),
                'statusCounts' => $this->statusCounts($tenant->id, $request->user()->id),
            ],
        ]);
    }

    public function show(Request $request, CurrentTenant $currentTenant, int $review): SourcingIntakeReviewResource
    {
        $model = $this->findTenantReview($this->tenantOrAbort($currentTenant), $review);
        $this->authorize('view', $model);

        return new SourcingIntakeReviewResource($model);
    }

    public function storeForRequisition(Request $request, CurrentTenant $currentTenant, int $requisition, CreateOrRevealSourcingIntakeReview $action): JsonResponse
    {
        $this->authorize('create', SourcingIntakeReview::class);
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisitionModel = Requisition::query()->where('tenant_id', $tenant->id)->findOrFail($requisition);
        $result = $action->handle($tenant, $request->user(), $requisitionModel);

        return (new SourcingIntakeReviewResource($result['review']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function claim(Request $request, CurrentTenant $currentTenant, int $review, ClaimSourcingIntakeReview $action): SourcingIntakeReviewResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantReview($tenant, $review);
        $this->authorize('update', $model);

        return new SourcingIntakeReviewResource($action->handle($tenant, $request->user(), $model));
    }

    public function reassign(ReassignSourcingIntakeReviewRequest $request, CurrentTenant $currentTenant, int $review, ReassignSourcingIntakeReview $action): SourcingIntakeReviewResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantReview($tenant, $review);
        $this->authorize('reassign', $model);

        return new SourcingIntakeReviewResource($action->handle($tenant, $request->user(), $model, (int) $request->validated('buyerId')));
    }

    public function update(UpdateSourcingIntakeReviewRequest $request, CurrentTenant $currentTenant, int $review, UpdateSourcingIntakeReview $action): SourcingIntakeReviewResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantReview($tenant, $review);
        $this->authorize('update', $model);

        return new SourcingIntakeReviewResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    public function decision(RecordSourcingIntakeDecisionRequest $request, CurrentTenant $currentTenant, int $review, RecordSourcingIntakeDecision $action): SourcingIntakeReviewResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantReview($tenant, $review);
        $this->authorize('decide', $model);

        return new SourcingIntakeReviewResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    public function close(CloseSourcingIntakeReviewRequest $request, CurrentTenant $currentTenant, int $review, CloseSourcingIntakeReview $action): SourcingIntakeReviewResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantReview($tenant, $review);
        $this->authorize('decide', $model);

        return new SourcingIntakeReviewResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    private function findTenantReview(Tenant $tenant, int $id): SourcingIntakeReview
    {
        return SourcingIntakeReview::query()
            ->with(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems'])
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
     * @return array<string, int>
     */
    private function statusCounts(int $tenantId, int $userId): array
    {
        $base = SourcingIntakeReview::query()->where('tenant_id', $tenantId);

        return [
            'open' => (clone $base)->where('status', SourcingIntakeStatus::Open)->count(),
            'in_review' => (clone $base)->where('status', SourcingIntakeStatus::InReview)->count(),
            'clarification_requested' => (clone $base)->where('status', SourcingIntakeStatus::ClarificationRequested)->count(),
            'ready_for_rfq' => (clone $base)->where('status', SourcingIntakeStatus::ReadyForRfq)->count(),
            'direct_award_recorded' => (clone $base)->where('status', SourcingIntakeStatus::DirectAwardRecorded)->count(),
            'closed' => (clone $base)->where('status', SourcingIntakeStatus::Closed)->count(),
            'unassigned' => (clone $base)->whereNull('assigned_buyer_id')->count(),
            'mine' => (clone $base)->where('assigned_buyer_id', $userId)->count(),
        ];
    }
}
