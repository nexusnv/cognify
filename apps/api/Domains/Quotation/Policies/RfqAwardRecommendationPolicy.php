<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;

class RfqAwardRecommendationPolicy
{
    public function view(User $user, Rfq|RfqAwardRecommendation $subject): bool
    {
        return $this->isTenantScoped($subject) && $this->buyerOrAdmin($user);
    }

    public function manage(User $user, Rfq|RfqAwardRecommendation $subject): bool
    {
        return $this->isTenantScoped($subject) && $this->buyerOrAdmin($user);
    }

    public function submit(User $user, RfqAwardRecommendation $recommendation): bool
    {
        return $this->isTenantScoped($recommendation)
            && $this->buyerOrAdmin($user)
            && $recommendation->statusState()->isEditable();
    }

    public function withdraw(User $user, RfqAwardRecommendation $recommendation): bool
    {
        return $this->isTenantScoped($recommendation)
            && $this->buyerOrAdmin($user)
            && $recommendation->statusState()->isPendingApproval();
    }

    private function buyerOrAdmin(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isTenantScoped(Rfq|RfqAwardRecommendation $subject): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();
        $tenantId = $subject instanceof Rfq ? $subject->tenant_id : $subject->tenant_id;

        return $tenant !== null && (int) $tenantId === (int) $tenant->id;
    }
}
