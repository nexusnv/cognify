<?php

namespace Domains\Approval\Http\Controllers;

use App\Auth\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\ApproveApprovalTask;
use Domains\Approval\Actions\DelegateApprovalTask;
use Domains\Approval\Actions\RejectApprovalTask;
use Domains\Approval\Actions\RequestApprovalChanges;
use Domains\Approval\Http\Requests\DelegateApprovalTaskRequest;
use Domains\Approval\Http\Resources\ApprovalTaskResource;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Models\RequisitionLineItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalTaskController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $validated = $request->validate([
            'scope' => ['sometimes', 'string', 'in:assigned_to_me,overdue,due_soon,completed_by_me,all'],
            'status' => ['sometimes', 'string'],
            'dueFrom' => ['sometimes', 'date'],
            'dueTo' => ['sometimes', 'date'],
            'requesterId' => ['sometimes', 'integer'],
            'department' => ['sometimes', 'string'],
            'costCenter' => ['sometimes', 'string'],
            'projectId' => ['sometimes', 'integer'],
            'amountMin' => ['sometimes', 'numeric'],
            'amountMax' => ['sometimes', 'numeric'],
            'updatedFrom' => ['sometimes', 'date'],
            'updatedTo' => ['sometimes', 'date'],
        ]);

        $query = ApprovalTask::query()
            ->with(['assignee', 'originalAssignee', 'decidedBy', 'stage', 'instance', 'subject'])
            ->where('tenant_id', $tenant->id)
            ->latest('updated_at');

        $scope = $validated['scope'] ?? 'assigned_to_me';
        $role = $currentTenant->roleFor($request->user());
        $canSeeAll = in_array($role, [TenantRole::Admin->value, TenantRole::Buyer->value], true);

        if ($scope === 'all' && ! $canSeeAll) {
            $scope = 'assigned_to_me';
        }

        match ($scope) {
            'overdue' => $query->where('assignee_id', $request->user()->id)->where('status', ApprovalTaskStatus::Active)->where('due_at', '<', now()),
            'due_soon' => $query->where('assignee_id', $request->user()->id)->where('status', ApprovalTaskStatus::Active)->whereBetween('due_at', [now(), now()->addDays(2)]),
            'completed_by_me' => $query->where('decided_by_id', $request->user()->id),
            'all' => null,
            default => $query->where('assignee_id', $request->user()->id),
        };

        $query
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['dueFrom'] ?? null, fn ($query, string $date) => $query->whereDate('due_at', '>=', $date))
            ->when($validated['dueTo'] ?? null, fn ($query, string $date) => $query->whereDate('due_at', '<=', $date))
            ->when($validated['updatedFrom'] ?? null, fn ($query, string $date) => $query->whereDate('updated_at', '>=', $date))
            ->when($validated['updatedTo'] ?? null, fn ($query, string $date) => $query->whereDate('updated_at', '<=', $date));

        foreach (['requesterId', 'department', 'costCenter', 'projectId'] as $filter) {
            if (! array_key_exists($filter, $validated)) {
                continue;
            }
            $column = match ($filter) {
                'requesterId' => 'requester_id',
                'costCenter' => 'cost_center',
                'projectId' => 'project_id',
                default => $filter,
            };
            $query->whereHasMorph('subject', [Requisition::class], fn ($subjectQuery) => $subjectQuery->where($column, $validated[$filter]));
        }

        if (array_key_exists('amountMin', $validated) || array_key_exists('amountMax', $validated)) {
            $matchingRequisitionIds = RequisitionLineItem::query()
                ->select('requisition_id')
                ->groupBy('requisition_id')
                ->when(
                    array_key_exists('amountMin', $validated),
                    fn ($amountQuery) => $amountQuery->havingRaw('SUM(quantity * estimated_unit_price) >= ?', [(float) $validated['amountMin']]),
                )
                ->when(
                    array_key_exists('amountMax', $validated),
                    fn ($amountQuery) => $amountQuery->havingRaw('SUM(quantity * estimated_unit_price) <= ?', [(float) $validated['amountMax']]),
                );

            $query->whereHasMorph(
                'subject',
                [Requisition::class],
                fn ($subjectQuery) => $subjectQuery->whereIn('id', $matchingRequisitionIds),
            );
        }

        $tasks = $query->paginate(20);

        return response()->json([
            'data' => ApprovalTaskResource::collection($tasks->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $tasks->currentPage(),
                'perPage' => $tasks->perPage(),
                'total' => $tasks->total(),
                'lastPage' => $tasks->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, CurrentTenant $currentTenant, int $approvalTask): ApprovalTaskResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $task = $this->findTenantTask($tenant, $approvalTask);

        abort_unless($this->canViewTask($request->user(), $currentTenant, $task), 403);

        return new ApprovalTaskResource($task);
    }

    public function view(Request $request, CurrentTenant $currentTenant, int $approvalTask): ApprovalTaskResource
    {
        $task = $this->findTenantTask($this->tenantOrAbort($currentTenant), $approvalTask);
        abort_unless((int) $task->assignee_id === (int) $request->user()->id, 403);
        $task->forceFill(['viewed_at' => $task->viewed_at ?? now()])->save();

        return new ApprovalTaskResource($task->refresh()->load(['assignee', 'originalAssignee', 'decidedBy', 'stage', 'instance', 'subject']));
    }

    public function approve(Request $request, CurrentTenant $currentTenant, ApproveApprovalTask $action, int $approvalTask): ApprovalTaskResource
    {
        $validated = $request->validate(['lockVersion' => ['required', 'integer']]);
        $tenant = $this->tenantOrAbort($currentTenant);

        return new ApprovalTaskResource($action->handle($tenant, $request->user(), $this->findTenantTask($tenant, $approvalTask), (int) $validated['lockVersion']));
    }

    public function reject(Request $request, CurrentTenant $currentTenant, RejectApprovalTask $action, int $approvalTask): ApprovalTaskResource
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3'],
        ]);
        $tenant = $this->tenantOrAbort($currentTenant);

        return new ApprovalTaskResource($action->handle($tenant, $request->user(), $this->findTenantTask($tenant, $approvalTask), (int) $validated['lockVersion'], $validated['reason']));
    }

    public function requestChanges(Request $request, CurrentTenant $currentTenant, RequestApprovalChanges $action, int $approvalTask): ApprovalTaskResource
    {
        $validated = $request->validate([
            'lockVersion' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3'],
            'requestedFields' => ['sometimes', 'array'],
            'requestedFields.*' => ['string'],
        ]);
        $tenant = $this->tenantOrAbort($currentTenant);

        return new ApprovalTaskResource($action->handle($tenant, $request->user(), $this->findTenantTask($tenant, $approvalTask), (int) $validated['lockVersion'], $validated['reason'], $validated['requestedFields'] ?? []));
    }

    public function delegate(DelegateApprovalTaskRequest $request, CurrentTenant $currentTenant, DelegateApprovalTask $action, int $approvalTask): ApprovalTaskResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $validated = $request->validated();
        $task = $this->findTenantTask($tenant, $approvalTask);
        $delegation = ApprovalDelegation::query()
            ->with(['delegate', 'delegator'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail((int) $validated['approvalDelegationId']);

        return new ApprovalTaskResource($action->handle($tenant, $request->user(), $task, $delegation, (int) $validated['lockVersion']));
    }

    private function findTenantTask(Tenant $tenant, int $id): ApprovalTask
    {
        return ApprovalTask::query()
            ->with(['assignee', 'originalAssignee', 'decidedBy', 'stage', 'instance', 'subject'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        return $currentTenant->get();
    }

    private function canViewTask(User $user, CurrentTenant $currentTenant, ApprovalTask $task): bool
    {
        $role = $currentTenant->roleFor($user);

        if (in_array($role, [TenantRole::Admin->value, TenantRole::Buyer->value], true)) {
            return true;
        }

        return (int) $task->assignee_id === (int) $user->id
            || (int) $task->original_assignee_id === (int) $user->id;
    }
}
