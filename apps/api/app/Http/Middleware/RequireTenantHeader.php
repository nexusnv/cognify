<?php

namespace App\Http\Middleware;

use App\Tenancy\AmbiguousTenantException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantHeader
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-Id');
        $tenantId = $tenantId !== null ? trim($tenantId) : null;

        if ($tenantId === null || $tenantId === '') {
            throw new AmbiguousTenantException('X-Tenant-Id header is required for quotation scoring routes.');
        }

        return $next($request);
    }
}
