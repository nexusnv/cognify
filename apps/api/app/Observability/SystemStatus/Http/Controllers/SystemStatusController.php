<?php

namespace App\Observability\SystemStatus\Http\Controllers;

use App\Auth\TenantRole;
use App\Http\Controllers\Controller;
use App\Observability\SystemStatus\Http\Resources\SystemStatusResource;
use App\Observability\SystemStatus\SystemStatusService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemStatusController extends Controller
{
    public function show(Request $request, CurrentTenant $currentTenant, SystemStatusService $service): JsonResource
    {
        $tenant = $currentTenant->get();
        $user = $request->user();
        abort_unless($user !== null, 401, 'Authentication is required.');
        abort_unless($tenant->roleFor($user) === TenantRole::Admin->value, 403, 'You are not allowed to perform this action.');

        return new SystemStatusResource($service->build($tenant));
    }
}
