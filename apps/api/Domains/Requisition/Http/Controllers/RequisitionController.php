<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Requisition\CreateRequisitionRequest;
use App\Http\Requests\Requisition\UpdateRequisitionRequest;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Requisition\Actions\ApplyRequisitionTemplate;
use Domains\Requisition\Actions\CancelRequisition;
use Domains\Requisition\Actions\CreateRequisitionDraft;
use Domains\Requisition\Actions\RequestRequisitionChanges;
use Domains\Requisition\Actions\ResubmitRequisition;
use Domains\Requisition\Actions\SubmitRequisition;
use Domains\Requisition\Actions\UpdateRequisitionDraft;
use Domains\Requisition\Actions\WithdrawRequisition;
use Domains\Requisition\Exceptions\DraftConflictException;
use Domains\Requisition\Http\Requests\ApplyRequisitionTemplateRequest;
use Domains\Requisition\Http\Requests\ReasonedRequisitionActionRequest;
use Domains\Requisition\Http\Requests\RequestRequisitionChangesRequest;
use Domains\Requisition\Http\Resources\RequisitionResource;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Models\RequisitionTemplate;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequisitionController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant)
    {
        $this->authorize('viewAny', Requisition::class);

        $tenant = $this->tenantOrAbort($currentTenant);
        $user = $request->user();
        $role = $currentTenant->roleFor($user);
        $amountMin = $request->query('amountMin');
        $amountMax = $request->query('amountMax');

        $query = Requisition::query()
            ->select('requisitions.*')
            ->with(['requester', 'lineItems', 'changesRequestedBy', 'withdrawnBy', 'cancelledBy'])
            ->where('requisitions.tenant_id', $tenant->id)
            ->latest('requisitions.updated_at');

        if ($role === 'buyer' || $role === 'approver') {
            $query->where('requisitions.status', RequisitionStatus::Submitted);
        } elseif ($role !== 'admin') {
            $query->where('requisitions.requester_id', $user->id);
        }

        $query->when($request->query('search'), function ($query, string $search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        });

        $query->when($request->query('status'), fn ($query, string $status) => $query->where('requisitions.status', $status));
        $query->when($request->query('requester'), fn ($query, string $requester) => $query->where('requisitions.requester_id', $requester));
        $query->when($request->query('owner'), fn ($query, string $owner) => $query->where('requisitions.requester_id', $owner));
        $query->when($request->query('department'), fn ($query, string $department) => $query->where('requisitions.department', $department));
        $query->when($request->query('neededByFrom'), fn ($query, string $date) => $query->whereDate('requisitions.needed_by_date', '>=', $date));
        $query->when($request->query('neededByTo'), fn ($query, string $date) => $query->whereDate('requisitions.needed_by_date', '<=', $date));
        $query->when($request->query('updatedFrom'), fn ($query, string $date) => $query->whereDate('requisitions.updated_at', '>=', $date));
        $query->when($request->query('updatedTo'), fn ($query, string $date) => $query->whereDate('requisitions.updated_at', '<=', $date));
        if ($amountMin !== null || $amountMax !== null) {
            $estimatedTotalSql = '(select cast(coalesce(sum(quantity * estimated_unit_price), 0) as real) from requisition_line_items where requisition_line_items.requisition_id = requisitions.id)';

            if ($amountMin !== null && $amountMax !== null) {
                $query->whereRaw("{$estimatedTotalSql} between ? and ?", [(float) $amountMin, (float) $amountMax]);
            } elseif ($amountMin !== null) {
                $query->whereRaw("{$estimatedTotalSql} >= ?", [(float) $amountMin]);
            } else {
                $query->whereRaw("{$estimatedTotalSql} <= ?", [(float) $amountMax]);
            }
        }

        match ($request->query('queuePreset')) {
            'my_drafts' => $query->where('requisitions.requester_id', $user->id)->where('requisitions.status', RequisitionStatus::Draft),
            'submitted' => $query->where('requisitions.status', RequisitionStatus::Submitted),
            'needs_my_correction' => $query->where('requisitions.requester_id', $user->id)->where('requisitions.status', RequisitionStatus::ChangesRequested),
            'buyer_review' => $query->where('requisitions.status', RequisitionStatus::Submitted),
            'stopped' => $query->whereIn('requisitions.status', [RequisitionStatus::Withdrawn, RequisitionStatus::Cancelled]),
            default => null,
        };

        $perPage = max(1, min($request->integer('perPage', 15), 100));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => RequisitionResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(
        CreateRequisitionRequest $request,
        CurrentTenant $currentTenant,
        CreateRequisitionDraft $createRequisitionDraft,
    ): JsonResponse {
        $requisition = $createRequisitionDraft->handle(
            $this->tenantOrAbort($currentTenant),
            $request->user(),
            $request->validated(),
        );

        return (new RequisitionResource($requisition))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CurrentTenant $currentTenant, int $requisition): RequisitionResource
    {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('view', $requisition);

        return new RequisitionResource($requisition->load(['requester', 'lineItems', 'changesRequestedBy', 'withdrawnBy', 'cancelledBy']));
    }

    public function update(
        UpdateRequisitionRequest $request,
        CurrentTenant $currentTenant,
        UpdateRequisitionDraft $updateRequisitionDraft,
        int $requisition,
    ): JsonResponse|RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('update', $requisition);

        try {
            $requisition = $updateRequisitionDraft->handle(
                $currentTenant->get(),
                $request->user(),
                $requisition,
                $request->validated(),
            );
        } catch (DraftConflictException $exception) {
            return $this->draftConflictResponse($exception->getMessage());
        }

        return new RequisitionResource($requisition);
    }

    public function applyTemplate(
        ApplyRequisitionTemplateRequest $request,
        CurrentTenant $currentTenant,
        ApplyRequisitionTemplate $applyRequisitionTemplate,
        int $requisition,
    ): JsonResponse|RequisitionResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('update', $requisition);

        $template = RequisitionTemplate::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->findOrFail((int) $request->validated('templateId'));

        try {
            $requisition = $applyRequisitionTemplate->handle(
                $tenant,
                $request->user(),
                $requisition,
                $template,
                $request->validated('mode'),
                (int) $request->validated('lockVersion'),
            );
        } catch (DraftConflictException $exception) {
            return $this->draftConflictResponse($exception->getMessage());
        }

        return new RequisitionResource($requisition);
    }

    public function submit(
        Request $request,
        CurrentTenant $currentTenant,
        SubmitRequisition $submitRequisition,
        int $requisition,
    ): RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('submit', $requisition);

        $requisition = $submitRequisition->handle(
            $this->tenantOrAbort($currentTenant),
            $request->user(),
            $requisition,
        );

        return new RequisitionResource($requisition);
    }

    public function requestChanges(
        RequestRequisitionChangesRequest $request,
        CurrentTenant $currentTenant,
        RequestRequisitionChanges $requestRequisitionChanges,
        int $requisition,
    ): RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('requestChanges', $requisition);

        return new RequisitionResource($requestRequisitionChanges->handle(
            $this->tenantOrAbort($currentTenant),
            $request->user(),
            $requisition,
            $request->validated(),
        ));
    }

    public function resubmit(
        Request $request,
        CurrentTenant $currentTenant,
        ResubmitRequisition $resubmitRequisition,
        int $requisition,
    ): RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('resubmit', $requisition);

        return new RequisitionResource($resubmitRequisition->handle(
            $this->tenantOrAbort($currentTenant),
            $request->user(),
            $requisition,
        ));
    }

    public function withdraw(
        ReasonedRequisitionActionRequest $request,
        CurrentTenant $currentTenant,
        WithdrawRequisition $withdrawRequisition,
        int $requisition,
    ): RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('withdraw', $requisition);

        return new RequisitionResource($withdrawRequisition->handle(
            $this->tenantOrAbort($currentTenant),
            $request->user(),
            $requisition,
            (string) $request->validated('reason'),
        ));
    }

    public function cancel(
        ReasonedRequisitionActionRequest $request,
        CurrentTenant $currentTenant,
        CancelRequisition $cancelRequisition,
        int $requisition,
    ): RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('cancel', $requisition);

        return new RequisitionResource($cancelRequisition->handle(
            $this->tenantOrAbort($currentTenant),
            $request->user(),
            $requisition,
            (string) $request->validated('reason'),
        ));
    }

    private function findTenantRequisition(CurrentTenant $currentTenant, int $id): Requisition
    {
        $tenant = $this->tenantOrAbort($currentTenant);

        return Requisition::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }

    private function draftConflictResponse(string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'draft_conflict',
                'message' => $message,
            ],
        ], 409);
    }
}
