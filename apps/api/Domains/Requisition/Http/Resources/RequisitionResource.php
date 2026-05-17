<?php

namespace Domains\Requisition\Http\Resources;

use App\Models\User;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin \Domains\Requisition\Models\Requisition
 */
class RequisitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lineItems = $this->whenLoaded('lineItems');

        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'tenantId' => (string) $this->tenant_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'lockVersion' => $this->lock_version,
            'businessJustification' => $this->business_justification,
            'neededByDate' => $this->needed_by_date?->toDateString(),
            'department' => $this->department,
            'projectId' => $this->project_id !== null ? (string) $this->project_id : null,
            'costCenter' => $this->cost_center,
            'deliveryLocation' => $this->delivery_location,
            'currency' => $this->currency,
            'projectSummary' => $this->projectSummary($this->whenLoaded('project')),
            'requester' => [
                'id' => (string) $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ],
            'lineItems' => RequisitionLineItemResource::collection($lineItems),
            'estimatedTotal' => $this->estimatedTotal(),
            'changesRequestedAt' => $this->changes_requested_at?->toISOString(),
            'changesRequestedBy' => $this->userSummary($this->whenLoaded('changesRequestedBy')),
            'changeRequestReason' => $this->change_request_reason,
            'changeRequestFields' => $this->change_request_fields ?? [],
            'withdrawnAt' => $this->withdrawn_at?->toISOString(),
            'withdrawnBy' => $this->userSummary($this->whenLoaded('withdrawnBy')),
            'withdrawalReason' => $this->withdrawal_reason,
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelledBy' => $this->userSummary($this->whenLoaded('cancelledBy')),
            'cancellationReason' => $this->cancellation_reason,
            'permissions' => [
                'canUpdate' => in_array($this->status, [RequisitionStatus::Draft, RequisitionStatus::ChangesRequested], true)
                    && ($request->user()?->can('update', $this->resource) ?? false),
                'canSubmit' => $this->status === RequisitionStatus::Draft
                    && ($request->user()?->can('submit', $this->resource) ?? false),
                'canResubmit' => $this->status === RequisitionStatus::ChangesRequested
                    && ($request->user()?->can('resubmit', $this->resource) ?? false),
                'canRequestChanges' => $this->status === RequisitionStatus::Submitted
                    && ($request->user()?->can('requestChanges', $this->resource) ?? false),
                'canWithdraw' => in_array($this->status, [
                    RequisitionStatus::Draft,
                    RequisitionStatus::Submitted,
                    RequisitionStatus::ChangesRequested,
                ], true) && ($request->user()?->can('withdraw', $this->resource) ?? false),
                'canCancel' => in_array($this->status, [RequisitionStatus::Submitted, RequisitionStatus::ChangesRequested], true)
                    && ($request->user()?->can('cancel', $this->resource) ?? false),
                'canComment' => $request->user()?->can('comment', $this->resource) ?? false,
                'canMention' => $request->user()?->can('mention', $this->resource) ?? false,
                'canViewActivity' => $request->user()?->can('view', $this->resource) ?? false,
            ],
            'submittedAt' => $this->submitted_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function projectSummary(ProcurementProject|MissingValue|null $project): ?array
    {
        if (! $project instanceof ProcurementProject) {
            return null;
        }

        return [
            'id' => (string) $project->id,
            'number' => $project->number,
            'name' => $project->name,
            'status' => $project->status->value,
            'owner' => $this->userSummary($project->relationLoaded('owner') ? $project->owner : null),
        ];
    }

    private function estimatedTotal(): float
    {
        if (! $this->relationLoaded('lineItems') || $this->lineItems->isEmpty()) {
            return 0.0;
        }

        $total = $this->lineItems->reduce(
            fn (float $carry, $lineItem): float => $carry + ((float) $lineItem->quantity * (float) $lineItem->estimated_unit_price),
            0.0,
        );

        return round($total, 2);
    }

    /**
     * @param User|MissingValue|null $user
     * @return array{id: string, name: string, email: string|null}|null
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
