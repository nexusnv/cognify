<?php

namespace App\Tenancy;

use App\Models\User;

class CurrentTenant
{
    public function __construct(private ?Tenant $tenant = null) {}

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        abort_unless($this->tenant instanceof Tenant, 400, 'A tenant context is required.');

        return $this->tenant;
    }

    public function nullable(): ?Tenant
    {
        return $this->tenant;
    }

    public function roleFor(User $user): ?string
    {
        return $this->get()->roleFor($user);
    }

    public function userIsMember(User $user): bool
    {
        return $this->roleFor($user) !== null;
    }
}
