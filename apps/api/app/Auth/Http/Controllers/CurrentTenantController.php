<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Resources\CurrentUserResource;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentTenantController
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['tenantId' => ['required', 'string']]);

        $user = $request->user();
        $tenant = $user->tenants()->whereKey($request->input('tenantId'))->first();

        abort_unless($tenant !== null, 403, 'Tenant membership is required.');

        $this->currentTenant->set($tenant);

        return response()->json([
            'data' => new CurrentUserResource($user),
        ]);
    }
}
