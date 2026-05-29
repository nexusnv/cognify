<?php

namespace Domains\Approval\Contracts;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Illuminate\Database\Eloquent\Model;

interface ApprovalSubjectHandler
{
    public function subjectType(): string;

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string;

    public function buildContext(Model $subject): ApprovalContextData;

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary;

    public function taskTitle(Model $subject): string;

    public function notificationSubjectLabel(Model $subject): ?string;

    public function notificationBody(Model $subject): string;

    public function canDelegateTo(Model $subject, User $delegate): bool;

    public function delegationValidationMessage(Model $subject): string;

    /**
     * @param  array<string, mixed>  $stageTemplate
     * @return iterable<int, User>
     */
    public function escalationFallbackRecipients(Tenant $tenant, Model $subject, array $stageTemplate): iterable;

    public function onRouted(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void;

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void;

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void;

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void;
}
