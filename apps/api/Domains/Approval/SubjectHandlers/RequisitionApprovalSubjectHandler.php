<?php

namespace Domains\Approval\SubjectHandlers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Domains\Requisition\Actions\MarkRequisitionApproved;
use Domains\Requisition\Actions\MarkRequisitionPendingApproval;
use Domains\Requisition\Actions\MarkRequisitionRejected;
use Domains\Requisition\Actions\RequestRequisitionChanges;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class RequisitionApprovalSubjectHandler implements ApprovalSubjectHandler
{
    public function __construct(
        private readonly MarkRequisitionPendingApproval $markPendingApproval,
        private readonly MarkRequisitionApproved $markApproved,
        private readonly MarkRequisitionRejected $markRejected,
        private readonly RequestRequisitionChanges $requestChanges,
    ) {}

    public function subjectType(): string
    {
        return 'requisition';
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return Requisition::class;
    }

    public function buildContext(Model $subject): ApprovalContextData
    {
        assert($subject instanceof Requisition);

        return ApprovalContextData::fromRequisition($subject);
    }

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
    {
        assert($subject instanceof Requisition);

        $subject->loadMissing(['requester', 'lineItems']);

        return new ApprovalSubjectSummary(
            type: 'requisition',
            id: (string) $subject->id,
            number: $subject->number,
            title: $subject->title,
            status: $subject->status->value,
            primaryParty: $subject->requester?->name,
            amount: round($subject->lineItems->reduce(
                fn (float $carry, $lineItem): float => $carry + ((float) $lineItem->quantity * (float) $lineItem->estimated_unit_price),
                0.0,
            ), 2),
            currency: $subject->currency,
            href: "/requisitions/{$subject->id}",
            metadata: [
                'requester' => $subject->requester !== null ? [
                    'id' => (string) $subject->requester->id,
                    'name' => $subject->requester->name,
                    'email' => $subject->requester->email,
                ] : null,
                'department' => $subject->department,
                'costCenter' => $subject->cost_center,
                'projectId' => $subject->project_id !== null ? (string) $subject->project_id : null,
            ],
        );
    }

    public function taskTitle(Model $subject): string
    {
        assert($subject instanceof Requisition);

        return sprintf('Approve %s', $subject->number);
    }

    public function notificationSubjectLabel(Model $subject): ?string
    {
        assert($subject instanceof Requisition);

        return $subject->number;
    }

    public function notificationBody(Model $subject): string
    {
        assert($subject instanceof Requisition);

        return $subject->title;
    }

    public function canDelegateTo(Model $subject, User $delegate): bool
    {
        return ! $subject instanceof Requisition || (int) $subject->requester_id !== (int) $delegate->id;
    }

    public function delegationValidationMessage(Model $subject): string
    {
        return 'The delegate cannot be the requester of the requisition.';
    }

    public function escalationFallbackRecipients(Tenant $tenant, Model $subject, array $stageTemplate): iterable
    {
        $fallbackApprovers = collect($stageTemplate['fallbackApprovers'] ?? []);

        if ($fallbackApprovers->isEmpty()) {
            return $this->usersForRole($tenant, 'buyer')
                ->merge($this->usersForRole($tenant, 'admin'))
                ->unique('id')
                ->values();
        }

        return $this->resolveFallbackApprovers($tenant, $fallbackApprovers);
    }

    public function onRouted(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof Requisition);

        $this->markPendingApproval->handle($subject, $instance, $actor);
    }

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof Requisition);

        $this->markApproved->handle($subject, $instance, $actor);
    }

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
    {
        assert($subject instanceof Requisition);

        $this->markRejected->handle($subject, $instance, $actor, $reason);
    }

    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        assert($subject instanceof Requisition);

        $this->requestChanges->handle($tenant, $actor, $subject, [
            'reason' => $reason,
            'requestedFields' => $requestedFields,
            'approvalInstanceId' => $instance->id,
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $fallbackApprovers
     * @return Collection<int, User>
     */
    private function resolveFallbackApprovers(Tenant $tenant, Collection $fallbackApprovers): Collection
    {
        return $fallbackApprovers
            ->flatMap(function (mixed $approver) use ($tenant): Collection {
                if (! is_array($approver)) {
                    return collect();
                }

                if (($approver['type'] ?? null) === 'user' && isset($approver['userId'])) {
                    $user = $tenant->users()->whereKey((int) $approver['userId'])->first();

                    return $user instanceof User ? collect([$user]) : collect();
                }

                if (($approver['type'] ?? null) === 'role' && isset($approver['role'])) {
                    return $this->usersForRole($tenant, (string) $approver['role']);
                }

                return collect();
            })
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersForRole(Tenant $tenant, string $role): Collection
    {
        return $tenant->users()
            ->wherePivot('role', $role)
            ->orderBy('users.id')
            ->get();
    }
}
