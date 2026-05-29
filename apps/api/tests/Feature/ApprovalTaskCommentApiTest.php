<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalTaskCommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_approver_can_list_and_create_task_comments(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $task = $this->approvalTask($tenant, $requester, $approver);

        CollaborationComment::query()->create([
            'tenant_id' => $tenant->id,
            'subject_type' => ApprovalTask::class,
            'subject_id' => $task->id,
            'author_id' => $approver->id,
            'body' => 'Existing approval note.',
        ]);

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/approval-tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.subjectType', 'approval_task')
            ->assertJsonPath('data.0.body', 'Existing approval note.');

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/comments", [
                'body' => 'I checked budget alignment.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subjectType', 'approval_task')
            ->assertJsonPath('data.subjectId', (string) $task->id)
            ->assertJsonPath('data.author.id', (string) $approver->id)
            ->assertJsonPath('data.body', 'I checked budget alignment.');

        $this->assertDatabaseHas('collaboration_comments', [
            'tenant_id' => $tenant->id,
            'subject_type' => ApprovalTask::class,
            'subject_id' => $task->id,
            'author_id' => $approver->id,
            'body' => 'I checked budget alignment.',
        ]);
    }

    public function test_tenant_admin_can_list_task_comments(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $admin] = $this->tenantUser('admin', $tenant);
        $task = $this->approvalTask($tenant, $requester, $approver);

        CollaborationComment::query()->create([
            'tenant_id' => $tenant->id,
            'subject_type' => ApprovalTask::class,
            'subject_id' => $task->id,
            'author_id' => $approver->id,
            'body' => 'Ready for admin review.',
        ]);

        $this->actingAsTenant($tenant, $admin)
            ->getJson("/api/approval-tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Ready for admin review.');
    }

    public function test_non_visible_tenant_member_cannot_comment(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [, $otherMember] = $this->tenantUser('approver', $tenant);
        $task = $this->approvalTask($tenant, $requester, $approver);

        $this->actingAsTenant($tenant, $otherMember)
            ->postJson("/api/approval-tasks/{$task->id}/comments", [
                'body' => 'I should not see this task.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('collaboration_comments', [
            'tenant_id' => $tenant->id,
            'subject_type' => ApprovalTask::class,
            'subject_id' => $task->id,
            'author_id' => $otherMember->id,
        ]);
    }

    public function test_task_comments_are_tenant_scoped(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');
        [, $otherApprover] = $this->tenantUser('approver', $otherTenant);
        $task = $this->approvalTask($tenant, $requester, $approver);
        $otherTask = $this->approvalTask($otherTenant, $otherRequester, $otherApprover);

        CollaborationComment::query()->create([
            'tenant_id' => $tenant->id,
            'subject_type' => ApprovalTask::class,
            'subject_id' => $task->id,
            'author_id' => $approver->id,
            'body' => 'Tenant note.',
        ]);
        CollaborationComment::query()->create([
            'tenant_id' => $otherTenant->id,
            'subject_type' => ApprovalTask::class,
            'subject_id' => $otherTask->id,
            'author_id' => $otherApprover->id,
            'body' => 'Other tenant note.',
        ]);

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/approval-tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Tenant note.');

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/approval-tasks/{$otherTask->id}/comments")
            ->assertNotFound();
    }

    public function test_task_comment_creation_writes_audit_event(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $approver] = $this->tenantUser('approver', $tenant);
        $task = $this->approvalTask($tenant, $requester, $approver);

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/comments", [
                'body' => 'Approved once sourcing confirms lead time.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $approver->id,
            'event_type' => 'collaboration.comment_created',
            'subject_type' => ApprovalTask::class,
            'subject_id' => $task->id,
            'subject_display' => $task->title,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function approvalTask(Tenant $tenant, User $requester, User $approver): ApprovalTask
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => sprintf('REQ-2026-%06d', Requisition::query()->where('tenant_id', $tenant->id)->count() + 1),
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'MYR',
            'status' => RequisitionStatus::PendingApproval,
            'lock_version' => 0,
        ]);

        return ApprovalTask::query()->create([
            'tenant_id' => $tenant->id,
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
            'assignee_id' => $approver->id,
            'original_assignee_id' => $approver->id,
            'title' => 'Approve '.$requisition->number,
            'status' => ApprovalTaskStatus::Active,
            'assigned_at' => now(),
            'lock_version' => 0,
            'metadata' => [],
        ]);
    }
}
