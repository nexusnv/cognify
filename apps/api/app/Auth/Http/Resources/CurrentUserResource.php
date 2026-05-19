<?php

namespace App\Auth\Http\Resources;

use App\Auth\Permissions\TenantPermissionResolver;
use App\Models\User;
use App\Notifications\NotificationPreferenceDefaults;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentTenant = app(CurrentTenant::class);
        $resolver = app(TenantPermissionResolver::class);
        $tenant = $currentTenant->nullable();
        $user = $request->user();
        $memberships = $this->tenants->map(fn ($tenant) => [
            'id' => (string) $tenant->id,
            'name' => $tenant->name,
            'role' => $tenant->pivot->role,
        ]);
        $role = $tenant ? $currentTenant->roleFor($user) : null;

        return [
            'user' => [
                'id' => (string) $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'avatarUrl' => $this->avatar_url,
                'timezone' => $this->timezone,
                'locale' => $this->locale,
                'theme' => $this->theme,
                'notificationPreferences' => NotificationPreferenceDefaults::merge($this->notification_preferences),
            ],
            'tenants' => $memberships,
            'activeTenant' => $tenant ? ['id' => (string) $tenant->id, 'name' => $tenant->name] : null,
            'activeRole' => $role,
            'permissions' => $resolver->forRole($role),
        ];
    }
}
