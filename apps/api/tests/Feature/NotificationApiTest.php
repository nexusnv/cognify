<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_creates_one_notification_per_unique_recipient_with_defaults(): void
    {
        [$tenant, $actor] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: [$buyer, $buyer],
            data: new NotificationData(
                type: 'system.announcement',
                title: 'Maintenance window scheduled',
                body: 'Cognify demo data will refresh tonight at 23:00.',
                actor: $actor,
            ),
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $buyer->id,
            'actor_id' => $actor->id,
            'type' => 'system.announcement',
            'title' => 'Maintenance window scheduled',
            'priority' => 'normal',
        ]);
    }

    public function test_recorder_skips_disabled_in_app_preferences(): void
    {
        [$tenant, $actor] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $buyer->forceFill([
            'notification_preferences' => [
                'requisition.submitted' => ['inApp' => false],
                'attachment.uploaded' => ['inApp' => true],
                'system.announcement' => ['inApp' => true],
            ],
        ])->save();

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: [$buyer],
            data: new NotificationData(
                type: 'requisition.submitted',
                title: 'Requisition submitted',
                body: 'REQ-2026-000001 is ready for procurement review.',
                actor: $actor,
            ),
        );

        $this->assertDatabaseCount('notifications', 0);
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
}
