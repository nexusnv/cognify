<?php

namespace App\Providers;

use App\Audit\AuditEvent;
use App\Audit\Policies\AuditEventPolicy;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Policies\RequisitionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Requisition::class, RequisitionPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
    }
}
