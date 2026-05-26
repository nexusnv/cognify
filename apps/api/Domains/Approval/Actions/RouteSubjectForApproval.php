<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Auth\TenantRole;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalStage;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\Services\ApprovalPolicyMatcher;
use Domains\Approval\Services\ApprovalRouteBuilder;
use Domains\Approval\Services\ApprovalSubjectRegistry;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RouteSubjectForApproval
{
    public function __construct(
        private readonly ApprovalSubjectRegistry $subjectRegistry,
        private readonly ApprovalPolicyMatcher $matcher,
        private readonly ApprovalRouteBuilder $routeBuilder,
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {}

    public function handle(Tenant $tenant, User $actor, Model $subject): ApprovalInstance
    {
        return DB::transaction(function () use ($tenant, $actor, $subject): ApprovalInstance {
            $handler = $this->subjectRegistry->forSubject($subject);
            $modelClass = $handler->modelClass();

            $subject = $modelClass::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($subject->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = ApprovalInstance::query()
                ->with(['stages.tasks.assignee', 'tasks.assignee'])
                ->where('tenant_id', $tenant->id)
                ->where('subject_type', $modelClass)
                ->where('subject_id', $subject->getKey())
                ->where('status', ApprovalInstanceStatus::Active)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $context = $handler->buildContext($subject);
            $policyCandidates = $this->tenantPolicyCandidates($tenant, $handler);
            if ($policyCandidates === []) {
                throw new ConflictHttpException('No approval policy versions are available.');
            }

            $match = $this->matcher->match($context, $policyCandidates);
            $route = $this->routeBuilder->build(
                $context,
                $match['matchedVersion'],
                $match['matchedConditions'],
                $match['warnings'],
            );

            if ($route['stages'] === []) {
                throw new ConflictHttpException('Approval policy version does not define any stages.');
            }

            $instance = ApprovalInstance::query()->create([
                'tenant_id' => $tenant->id,
                'subject_type' => $modelClass,
                'subject_id' => $subject->getKey(),
                'approval_policy_version_id' => $match['matchedVersion']['id'] ?? null,
                'status' => ApprovalInstanceStatus::Active,
                'current_stage_sequence' => 1,
                'matched_context' => $context->toArray(),
                'matched_explanation' => [
                    'matchedPolicy' => $match['matchedPolicy'],
                    'matchedVersion' => $match['matchedVersion'],
                    'matchedConditions' => $match['matchedConditions'],
                    'warnings' => $route['warnings'],
                ],
                'started_at' => now(),
            ]);

            foreach ($route['stages'] as $index => $stageData) {
                $sequence = $index + 1;
                $isActive = $sequence === 1;
                $stage = ApprovalStage::query()->create([
                    'tenant_id' => $tenant->id,
                    'approval_instance_id' => $instance->id,
                    'sequence' => $sequence,
                    'name' => $stageData['name'],
                    'completion_rule' => $stageData['completionRule'],
                    'status' => $isActive ? ApprovalStageStatus::Active : ApprovalStageStatus::Blocked,
                    'activated_at' => $isActive ? now() : null,
                    'due_at' => $isActive ? ($stageData['dueAt'] ?? null) : null,
                ]);

                $this->createStageTasks($tenant, $actor, $subject, $handler, $instance, $stage, $stageData, $isActive);
            }

            $handler->onRouted($tenant, $subject, $instance, $actor);
            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'approval_instance.routed',
                subject: $subject,
                metadata: ['approvalInstanceId' => (string) $instance->id],
            ));

            return $instance->load(['stages.tasks.assignee', 'tasks.assignee']);
        });
    }

    /**
     * @param  array<string, mixed>  $stageData
     */
    private function createStageTasks(
        Tenant $tenant,
        User $actor,
        Model $subject,
        ApprovalSubjectHandler $handler,
        ApprovalInstance $instance,
        ApprovalStage $stage,
        array $stageData,
        bool $isActive,
    ): void {
        $assignees = $this->resolveApprovers($tenant, $stageData['approvers'] ?? []);
        $fallbackApprovers = $stageData['fallbackApprovers'] ?? [];

        if ($assignees->isEmpty()) {
            $assignees = $this->resolveApprovers($tenant, $fallbackApprovers);
        }

        if ($assignees->isEmpty() && $fallbackApprovers === []) {
            $assignees = $tenant->users()->wherePivot('role', TenantRole::Approver->value)->get();
        }

        if ($assignees->isEmpty()) {
            throw new ConflictHttpException('Approval route did not resolve any active approvers.');
        }

        $tasks = $assignees->map(function (User $assignee) use ($tenant, $subject, $handler, $instance, $stage, $isActive): ApprovalTask {
            return ApprovalTask::query()->create([
                'tenant_id' => $tenant->id,
                'approval_instance_id' => $instance->id,
                'approval_stage_id' => $stage->id,
                'subject_type' => $handler->modelClass(),
                'subject_id' => $subject->getKey(),
                'assignee_id' => $assignee->id,
                'original_assignee_id' => $assignee->id,
                'title' => $handler->taskTitle($subject),
                'status' => $isActive ? ApprovalTaskStatus::Active : ApprovalTaskStatus::Blocked,
                'assigned_at' => $isActive ? now() : null,
                'due_at' => $isActive ? $stage->due_at : null,
                'metadata' => ['stageName' => $stage->name],
            ]);
        });

        if ($isActive) {
            $this->notificationRecorder->record($tenant, $assignees, new NotificationData(
                type: NotificationPreferenceDefaults::EVENT_APPROVAL_TASK_ASSIGNED,
                title: 'Approval task assigned',
                body: $handler->notificationBody($subject),
                href: "/approvals/tasks/{$tasks->first()?->id}",
                subject: $subject,
                subjectLabel: $handler->notificationSubjectLabel($subject),
                actor: $actor,
            ));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $approvers
     * @return Collection<int, User>
     */
    private function resolveApprovers(Tenant $tenant, array $approvers)
    {
        $users = collect();

        foreach ($approvers as $approver) {
            if (($approver['type'] ?? null) === 'user' && isset($approver['userId'])) {
                $user = $tenant->users()->whereKey((int) $approver['userId'])->first();
                if ($user instanceof User) {
                    $users->push($user);
                }
            }

            if (($approver['type'] ?? null) === 'role' && isset($approver['role'])) {
                $users = $users->merge($tenant->users()->wherePivot('role', (string) $approver['role'])->get());
            }
        }

        return $users->unique('id')->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tenantPolicyCandidates(Tenant $tenant, ApprovalSubjectHandler $handler): array
    {
        return ApprovalPolicyVersion::query()
            ->with('policy')
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', $handler->subjectType())
            ->where('status', ApprovalPolicyVersionStatus::Published)
            ->orderByDesc('priority')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (ApprovalPolicyVersion $version): array => [
                'matchedPolicy' => [
                    'id' => (string) $version->approval_policy_id,
                    'tenantId' => (string) $version->tenant_id,
                    'name' => $version->policy?->name ?? 'Approval policy',
                    'subjectType' => $version->subject_type,
                    'status' => $version->policy?->status->value ?? 'draft',
                ],
                'matchedVersion' => [
                    'id' => (string) $version->id,
                    'tenantId' => (string) $version->tenant_id,
                    'policyId' => (string) $version->approval_policy_id,
                    'versionNumber' => $version->version_number,
                    'status' => $version->status->value,
                    'priority' => $version->priority,
                    'rules' => $version->rules ?? [],
                    'routeTemplate' => $version->route_template ?? ['stages' => []],
                    'slaRules' => $version->sla_rules ?? [],
                ],
                'priority' => $version->priority,
                'rules' => $version->rules ?? [],
                'routeTemplate' => $version->route_template ?? ['stages' => []],
                'slaRules' => $version->sla_rules ?? [],
            ])
            ->all();
    }
}
