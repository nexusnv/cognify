<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\NotificationRecord;
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

    public function test_recorder_skips_unknown_notification_types(): void
    {
        [$tenant, $actor] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: [$buyer],
            data: new NotificationData(
                type: 'unknown.event',
                title: 'Unknown event',
                actor: $actor,
            ),
        );

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_system_announcement_can_omit_subject_and_href(): void
    {
        [$tenant, $user] = $this->tenantUser('buyer');

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: [$user],
            data: new NotificationData(
                type: 'system.announcement',
                title: 'Welcome to Cognify',
                body: 'Your procurement workspace is ready.',
            ),
        );

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Welcome to Cognify')
            ->assertJsonPath('data.0.href', null)
            ->assertJsonPath('data.0.subject', null);
    }

    public function test_user_lists_only_their_current_tenant_notifications_with_status_filters(): void
    {
        [$tenant, $user] = $this->tenantUser('buyer');
        [$otherTenant, $otherUser] = $this->tenantUser('buyer');

        NotificationRecord::query()->create([
            'tenant_id' => $tenant->id,
            'recipient_id' => $user->id,
            'type' => 'system.announcement',
            'title' => 'Unread tenant notice',
            'metadata' => [],
        ]);
        NotificationRecord::query()->create([
            'tenant_id' => $tenant->id,
            'recipient_id' => $user->id,
            'type' => 'system.announcement',
            'title' => 'Read tenant notice',
            'metadata' => [],
            'read_at' => now(),
        ]);
        NotificationRecord::query()->create([
            'tenant_id' => $otherTenant->id,
            'recipient_id' => $otherUser->id,
            'type' => 'system.announcement',
            'title' => 'Other tenant notice',
            'metadata' => [],
        ]);

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/notifications?status=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Unread tenant notice')
            ->assertJsonPath('meta.unreadCount', 1)
            ->assertJsonPath('meta.status', 'unread');
    }

    public function test_mark_one_read_is_idempotent_and_rejects_other_recipients(): void
    {
        [$tenant, $user] = $this->tenantUser('buyer');
        [, $otherUser] = $this->tenantUser('buyer', $tenant);
        $notification = NotificationRecord::query()->create([
            'tenant_id' => $tenant->id,
            'recipient_id' => $user->id,
            'type' => 'system.announcement',
            'title' => 'Unread tenant notice',
            'metadata' => [],
        ]);
        $otherNotification = NotificationRecord::query()->create([
            'tenant_id' => $tenant->id,
            'recipient_id' => $otherUser->id,
            'type' => 'system.announcement',
            'title' => 'Other recipient notice',
            'metadata' => [],
        ]);

        $first = $this->actingAsTenant($tenant, $user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->json('data.readAt');

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.readAt', $first);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/notifications/{$otherNotification->id}/read")
            ->assertNotFound();
    }

    public function test_mark_all_read_marks_only_current_user_current_tenant_notifications(): void
    {
        [$tenant, $user] = $this->tenantUser('buyer');
        [, $otherUser] = $this->tenantUser('buyer', $tenant);
        NotificationRecord::query()->create([
            'tenant_id' => $tenant->id,
            'recipient_id' => $user->id,
            'type' => 'system.announcement',
            'title' => 'First unread',
            'metadata' => [],
        ]);
        NotificationRecord::query()->create([
            'tenant_id' => $tenant->id,
            'recipient_id' => $otherUser->id,
            'type' => 'system.announcement',
            'title' => 'Other unread',
            'metadata' => [],
        ]);

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 1)
            ->assertJsonPath('meta.unreadCount', 0);

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $otherUser->id,
            'read_at' => null,
        ]);
    }

    public function test_current_user_response_includes_notification_preferences_with_defaults(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $response = $this->actingAsTenant($tenant, $user)
            ->getJson('/api/me')
            ->assertOk();

        $this->assertSame([
            'requisition.submitted' => ['inApp' => true],
            'attachment.uploaded' => ['inApp' => true],
            'system.announcement' => ['inApp' => true],
        ], $response->json('data.user.notificationPreferences'));
    }

    public function test_profile_update_validates_and_persists_notification_preferences(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->patchJson('/api/me/profile', [
                'name' => $user->name,
                'avatarUrl' => null,
                'timezone' => 'UTC',
                'locale' => 'en',
                'theme' => 'system',
                'notificationPreferences' => [
                    'requisition.submitted' => ['inApp' => true],
                    'attachment.uploaded' => ['inApp' => false],
                    'system.announcement' => ['inApp' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.name', $user->name);

        $this->assertSame(false, $user->fresh()->notification_preferences['attachment.uploaded']['inApp']);
    }

    public function test_profile_update_merges_partial_notification_preferences_without_resetting_existing_values(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->patchJson('/api/me/profile', [
                'name' => $user->name,
                'avatarUrl' => null,
                'timezone' => 'UTC',
                'locale' => 'en',
                'theme' => 'system',
                'notificationPreferences' => [
                    'requisition.submitted' => ['inApp' => false],
                    'attachment.uploaded' => ['inApp' => false],
                    'system.announcement' => ['inApp' => false],
                ],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $user)
            ->patchJson('/api/me/profile', [
                'name' => $user->name,
                'avatarUrl' => null,
                'timezone' => 'UTC',
                'locale' => 'en',
                'theme' => 'system',
                'notificationPreferences' => [
                    'requisition.submitted' => ['inApp' => true],
                ],
            ])
            ->assertOk();

        $this->assertSame([
            'requisition.submitted' => ['inApp' => true],
            'attachment.uploaded' => ['inApp' => false],
            'system.announcement' => ['inApp' => false],
        ], $user->fresh()->notification_preferences);
    }

    public function test_profile_update_rejects_unknown_notification_preference_keys(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->patchJson('/api/me/profile', [
                'name' => $user->name,
                'avatarUrl' => null,
                'timezone' => 'UTC',
                'locale' => 'en',
                'theme' => 'system',
                'notificationPreferences' => [
                    'unknown.event' => ['inApp' => true],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
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
