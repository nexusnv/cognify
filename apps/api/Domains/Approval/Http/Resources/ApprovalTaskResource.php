<?php

namespace Domains\Approval\Http\Resources;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\Services\ApprovalSubjectRegistry;
use Domains\Approval\States\ApprovalDelegationStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalTask
 */
class ApprovalTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subject = $this->whenLoaded('subject');
        $summary = $subject instanceof Model
            ? app(ApprovalSubjectRegistry::class)->forStoredSubject($this->subject_type)->taskSubjectSummary($subject)
            : null;
        $isActionable = $this->isActionableBy($request);

        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'instanceId' => (string) $this->approval_instance_id,
            'stageId' => (string) $this->approval_stage_id,
            'subject' => $summary?->toArray() ?? [
                'type' => $this->subject_type,
                'id' => (string) $this->subject_id,
                'number' => null,
                'title' => null,
                'status' => null,
                'primaryParty' => null,
                'amount' => null,
                'currency' => null,
                'href' => null,
                'metadata' => [],
            ],
            'title' => $this->title,
            'status' => $this->status->value,
            'decision' => $this->decision,
            'decisionReason' => $this->decision_reason,
            'requestedFields' => $this->requested_fields ?? [],
            'assignee' => $this->userSummary($this->whenLoaded('assignee')),
            'originalAssignee' => $this->userSummary($this->whenLoaded('originalAssignee')),
            'decidedBy' => $this->userSummary($this->whenLoaded('decidedBy')),
            'stage' => [
                'id' => (string) $this->approval_stage_id,
                'name' => $this->stage?->name,
                'sequence' => $this->stage?->sequence,
                'status' => $this->stage?->status?->value,
                'completionRule' => $this->stage?->completion_rule,
                'activatedAt' => $this->stage?->activated_at?->toISOString(),
                'completedAt' => $this->stage?->completed_at?->toISOString(),
                'dueAt' => $this->stage?->due_at?->toISOString(),
                'isActionable' => $this->stage?->status === ApprovalStageStatus::Active,
            ],
            'assignedAt' => $this->assigned_at?->toISOString(),
            'viewedAt' => $this->viewed_at?->toISOString(),
            'dueAt' => $this->due_at?->toISOString(),
            'decidedAt' => $this->decided_at?->toISOString(),
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'permissions' => [
                'canView' => $this->canViewTask($request),
                'canApprove' => $isActionable,
                'canReject' => $isActionable,
                'canRequestChanges' => $isActionable,
            ],
        ];
    }

    private function userSummary(mixed $user): ?array
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

    private function canViewTask(Request $request): bool
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return false;
        }

        $role = app(CurrentTenant::class)->roleFor($user);

        if (in_array($role, [TenantRole::Admin->value, TenantRole::Buyer->value], true)) {
            return true;
        }

        return (int) $this->assignee_id === (int) $user->id
            || (int) $this->original_assignee_id === (int) $user->id;
    }

    private function isActionableBy(Request $request): bool
    {
        $user = $request->user();

        if (! $user instanceof User || $this->status->value !== 'active' || (int) $this->assignee_id !== (int) $user->id) {
            return false;
        }

        if ((int) $this->original_assignee_id === (int) $this->assignee_id) {
            return true;
        }

        $delegationId = data_get($this->metadata, 'delegationId');

        return ApprovalDelegation::query()
            ->whereKey($delegationId)
            ->where('tenant_id', $this->tenant_id)
            ->where('delegator_id', $this->original_assignee_id)
            ->where('delegate_id', $user->id)
            ->where('status', ApprovalDelegationStatus::Active)
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();
    }
}
