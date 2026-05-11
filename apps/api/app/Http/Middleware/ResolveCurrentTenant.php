<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $tenantId = $request->header('X-Tenant-Id');
        $tenant = $tenantId
            ? Tenant::query()->whereKey($tenantId)->first()
            : $user->tenants()->first();

        abort_unless($tenant instanceof Tenant, 403, 'Tenant membership is required.');
        abort_unless($user->tenants()->whereKey($tenant->id)->exists(), 403, 'Tenant membership is required.');

        $this->currentTenant->set($tenant);

        return $next($request);
    }
}
