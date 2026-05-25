<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqScorecard;

class RfqScorecardPolicy
{
    public function view(User $user, RfqScorecard $scorecard): bool
    {
        return $this->scorecardInCurrentTenant($scorecard) && $this->canManageScoring($user);
    }

    public function create(User $user, Rfq $rfq): bool
    {
        return $this->rfqInCurrentTenant($rfq) && $this->canManageScoring($user);
    }

    public function update(User $user, RfqScorecard $scorecard): bool
    {
        return $this->scorecardInCurrentTenant($scorecard) && $this->canManageScoring($user);
    }

    public function complete(User $user, RfqScorecard $scorecard): bool
    {
        return $this->scorecardInCurrentTenant($scorecard) && $this->canManageScoring($user);
    }

    public function reopen(User $user, RfqScorecard $scorecard): bool
    {
        return $this->scorecardInCurrentTenant($scorecard) && $this->canManageScoring($user);
    }

    private function canManageScoring(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function rfqInCurrentTenant(Rfq $rfq): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $rfq->tenant_id === (int) $tenant->id;
    }

    private function scorecardInCurrentTenant(RfqScorecard $scorecard): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $scorecard->tenant_id === (int) $tenant->id;
    }
}
