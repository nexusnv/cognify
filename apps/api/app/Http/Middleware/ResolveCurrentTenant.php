<?php

namespace App\Http\Middleware;

use App\Tenancy\AmbiguousTenantException;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->currentTenant->clear();

        $user = $request->user();
        abort_unless($user !== null, 401);

        $membershipCount = DB::table('tenant_user')
            ->where('user_id', $user->id)
            ->count();

        $memberships = Tenant::query()
            ->whereIn('id', DB::table('tenant_user')->select('tenant_id')->where('user_id', $user->id));

        $tenantId = $request->header('X-Tenant-Id');
        if (is_array($tenantId)) {
            throw new AmbiguousTenantException('X-Tenant-Id header is required for users with multiple tenants.');
        }

        $tenantId = is_string($tenantId) ? trim($tenantId) : null;
        if ($tenantId === null || $tenantId === '') {
            if ($membershipCount > 1) {
                throw new AmbiguousTenantException('X-Tenant-Id header is required for users with multiple tenants.');
            }
        }

        $tenant = $tenantId !== null && $tenantId !== ''
            ? Tenant::query()->whereKey($tenantId)->first()
            : $memberships->first();

        abort_unless($tenant instanceof Tenant, 403, 'Tenant membership is required.');
        abort_unless($user->tenants()->whereKey($tenant->id)->exists(), 403, 'Tenant membership is required.');

        $this->currentTenant->set($tenant);

        return $next($request);
    }
}
