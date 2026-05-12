<?php

namespace App\Auth\Http\Resources;

use App\Auth\Permissions\TenantPermissionResolver;
use App\Models\User;
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
        $tenant = $currentTenant->get();
        $memberships = $this->tenants->map(fn ($tenant) => [
            'id' => (string) $tenant->id,
            'name' => $tenant->name,
            'role' => $tenant->pivot->role,
        ]);
        $role = $tenant?->pivot?->role;

        return [
            'user' => [
                'id' => (string) $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'avatarUrl' => $this->avatar_url,
                'timezone' => $this->timezone,
                'locale' => $this->locale,
                'theme' => $this->theme,
            ],
            'tenants' => $memberships,
            'activeTenant' => $tenant ? ['id' => (string) $tenant->id, 'name' => $tenant->name] : null,
            'activeRole' => $role,
            'permissions' => $resolver->forRole($role),
        ];
    }
}
