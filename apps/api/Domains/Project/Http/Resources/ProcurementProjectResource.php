<?php

namespace Domains\Project\Http\Resources;

use App\Models\User;
use Domains\Project\States\ProjectStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin \Domains\Project\Models\ProcurementProject
 */
class ProcurementProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'number' => $this->number,
            'name' => $this->name,
            'charter' => $this->charter,
            'status' => $this->status->value,
            'owner' => $this->userSummary($this->whenLoaded('owner')),
            'budgetAmount' => $this->budget_amount !== null ? (float) $this->budget_amount : null,
            'currency' => $this->currency,
            'department' => $this->department,
            'costCenter' => $this->cost_center,
            'targetStartDate' => $this->target_start_date?->toDateString(),
            'targetCompletionDate' => $this->target_completion_date?->toDateString(),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelledBy' => $this->userSummary($this->whenLoaded('cancelledBy')),
            'cancellationReason' => $this->cancellation_reason,
            'completedAt' => $this->completed_at?->toISOString(),
            'completedBy' => $this->userSummary($this->whenLoaded('completedBy')),
            'summary' => $this->summary(),
            'permissions' => [
                'canUpdate' => $request->user()?->can('update', $this->resource) ?? false,
                'canActivate' => $this->status === ProjectStatus::Draft && ($request->user()?->can('transition', $this->resource) ?? false),
                'canHold' => $this->status === ProjectStatus::Active && ($request->user()?->can('transition', $this->resource) ?? false),
                'canResume' => $this->status === ProjectStatus::OnHold && ($request->user()?->can('transition', $this->resource) ?? false),
                'canComplete' => in_array($this->status, [ProjectStatus::Active, ProjectStatus::OnHold], true) && ($request->user()?->can('transition', $this->resource) ?? false),
                'canCancel' => $request->user()?->can('cancel', $this->resource) ?? false,
                'canLinkRequisitions' => $request->user()?->can('linkRequisitions', $this->resource) ?? false,
                'canUnlinkRequisitions' => $request->user()?->can('unlinkRequisitions', $this->resource) ?? false,
                'canViewActivity' => $request->user()?->can('view', $this->resource) ?? false,
            ],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function summary(): array
    {
        $requisitions = $this->relationLoaded('requisitions')
            ? $this->requisitions
            : Requisition::query()->where('tenant_id', $this->tenant_id)->where('project_id', $this->id)->get();

        $estimated = $requisitions->sum(fn (Requisition $r) => (float) ($r->estimated_total ?? 0));

        return [
            'estimatedRequisitionTotal' => round($estimated, 2),
            'linkedRequisitionCount' => $requisitions->count(),
            'draftRequisitionCount' => $requisitions->where('status', RequisitionStatus::Draft)->count(),
            'submittedRequisitionCount' => $requisitions->where('status', RequisitionStatus::Submitted)->count(),
            'changesRequestedRequisitionCount' => $requisitions->where('status', RequisitionStatus::ChangesRequested)->count(),
            'stoppedRequisitionCount' => $requisitions->whereIn('status', [RequisitionStatus::Withdrawn, RequisitionStatus::Cancelled])->count(),
            'approvalPlaceholderCount' => 0,
            'awardPlaceholderCount' => 0,
        ];
    }

    /**
     * @param User|MissingValue|null $user
     */
    private function userSummary(User|MissingValue|null $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
