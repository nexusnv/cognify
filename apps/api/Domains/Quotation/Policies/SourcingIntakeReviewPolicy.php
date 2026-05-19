<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\SourcingIntakeReview;

class SourcingIntakeReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSourcing($user);
    }

    public function view(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageSourcing($user);
    }

    public function update(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    public function decide(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    public function reassign(User $user, SourcingIntakeReview $review): bool
    {
        return $this->canManageSourcing($user);
    }

    private function canManageSourcing(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }
}
