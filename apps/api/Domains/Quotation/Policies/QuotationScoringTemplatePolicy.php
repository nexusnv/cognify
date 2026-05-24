<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\QuotationScoringTemplate;

class QuotationScoringTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewTemplates($user);
    }

    public function view(User $user, QuotationScoringTemplate $template): bool
    {
        return $this->templateInCurrentTenant($template) && $this->canViewTemplates($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, QuotationScoringTemplate $template): bool
    {
        return $this->templateInCurrentTenant($template) && $this->isAdmin($user);
    }

    public function deactivate(User $user, QuotationScoringTemplate $template): bool
    {
        return $this->templateInCurrentTenant($template) && $this->isAdmin($user);
    }

    private function canViewTemplates(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isAdmin(User $user): bool
    {
        return app(CurrentTenant::class)->roleFor($user) === TenantRole::Admin->value;
    }

    private function templateInCurrentTenant(QuotationScoringTemplate $template): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $template->tenant_id === (int) $tenant->id;
    }
}
