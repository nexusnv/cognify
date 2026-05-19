<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CurrentUserController
{
    public function show(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $tenant = $this->resolveTenant($request, $user);
        if ($tenant instanceof Tenant) {
            $currentTenant->set($tenant);
        }

        return response()->json([
            'data' => new CurrentUserResource($user->loadMissing('tenants')),
        ]);
    }

    private function resolveTenant(Request $request, User $user): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-Id');

        if ($tenantId !== null && $tenantId !== '') {
            $tenant = Tenant::query()->whereKey($tenantId)->first();
            if (! $tenant instanceof Tenant) {
                throw new HttpException(404, 'Tenant not found.');
            }

            abort_unless($user->tenants()->whereKey($tenant->id)->exists(), 403, 'Tenant membership is required.');

            return $tenant;
        }

        $tenants = $user->tenants()->get();

        return $tenants->count() === 1 ? $tenants->first() : null;
    }
}
