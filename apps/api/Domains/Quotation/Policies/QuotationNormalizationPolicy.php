<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\QuotationNormalization;

class QuotationNormalizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canReview($user);
    }

    public function view(User $user, QuotationNormalization $normalization): bool
    {
        return $this->canReview($user) && $this->isTenantScoped($normalization);
    }

    public function update(User $user, QuotationNormalization $normalization): bool
    {
        return $this->view($user, $normalization);
    }

    public function approve(User $user, QuotationNormalization $normalization): bool
    {
        return $this->view($user, $normalization);
    }

    public function approveWithWarnings(User $user, QuotationNormalization $normalization): bool
    {
        return $this->view($user, $normalization);
    }

    public function createRevision(User $user, QuotationNormalization $normalization): bool
    {
        return $this->view($user, $normalization);
    }

    public function retry(User $user, QuotationNormalization $normalization): bool
    {
        return $this->view($user, $normalization);
    }

    private function canReview(User $user): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        if ($tenant === null) {
            return false;
        }

        return in_array($tenant->roleFor($user), [
            TenantRole::Buyer->value,
            TenantRole::Admin->value,
        ], true);
    }

    private function isTenantScoped(QuotationNormalization $normalization): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $normalization->tenant_id === (int) $tenant->id;
    }
}
