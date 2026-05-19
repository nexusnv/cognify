<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\States\SourcingIntakeStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class SourcingIntakeReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof SourcingIntakeStatus
            ? $this->status
            : SourcingIntakeStatus::from($this->status);
        $canEdit = ! $status->isTerminalForEditing();

        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'status' => $status->value,
            'sourcingPath' => $this->sourcing_path?->value ?? $this->sourcing_path,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'urgency' => $this->urgency,
            'complexity' => $this->complexity,
            'targetDecisionDate' => $this->target_decision_date?->toDateString(),
            'checklist' => $this->checklist ?? [],
            'internalNotes' => $this->internal_notes,
            'decisionReason' => $this->decision_reason,
            'clarificationMessage' => $this->clarification_message,
            'claimedAt' => $this->claimed_at?->toISOString(),
            'decidedAt' => $this->decided_at?->toISOString(),
            'closedAt' => $this->closed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'assignedBuyer' => $this->userSummary($this->whenLoaded('assignedBuyer')),
            'requisition' => $this->requisitionSummary($this->whenLoaded('requisition')),
            'project' => $this->projectSummary($this->whenLoaded('project')),
            'permissions' => [
                'canClaim' => $this->assigned_buyer_id === null && $status === SourcingIntakeStatus::Open,
                'canReassign' => true,
                'canUpdate' => $canEdit,
                'canRecordDecision' => $status === SourcingIntakeStatus::InReview,
                'canClose' => in_array($status, [
                    SourcingIntakeStatus::InReview,
                    SourcingIntakeStatus::ClarificationRequested,
                    SourcingIntakeStatus::ReadyForRfq,
                    SourcingIntakeStatus::DirectAwardRecorded,
                ], true),
                'canCreateRfq' => $status === SourcingIntakeStatus::ReadyForRfq,
            ],
        ];
    }

    private function userSummary(mixed $user): ?array
    {
        if ($user instanceof MissingValue || $user === null) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function projectSummary(mixed $project): ?array
    {
        if ($project instanceof MissingValue || $project === null) {
            return null;
        }

        return [
            'id' => (string) $project->id,
            'number' => $project->number,
            'name' => $project->name,
            'status' => $project->status?->value ?? $project->status,
        ];
    }

    private function requisitionSummary(mixed $requisition): ?array
    {
        if ($requisition instanceof MissingValue || $requisition === null) {
            return null;
        }

        $lineItems = $requisition->relationLoaded('lineItems') ? $requisition->lineItems : collect();

        return [
            'id' => (string) $requisition->id,
            'number' => $requisition->number,
            'title' => $requisition->title,
            'status' => $requisition->status?->value ?? $requisition->status,
            'requester' => $this->userSummary($requisition->relationLoaded('requester') ? $requisition->requester : null),
            'department' => $requisition->department,
            'neededByDate' => $requisition->needed_by_date?->toDateString(),
            'estimatedTotal' => $lineItems->sum(fn ($lineItem) => (float) $lineItem->quantity * (float) $lineItem->estimated_unit_price),
            'currency' => $requisition->currency,
        ];
    }
}
