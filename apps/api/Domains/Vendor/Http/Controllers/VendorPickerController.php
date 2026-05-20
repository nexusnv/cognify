<?php

namespace Domains\Vendor\Http\Controllers;

use App\Auth\TenantRole;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Vendor\Http\Requests\ListVendorsRequest;
use Domains\Vendor\Http\Resources\VendorPickerResource;
use Domains\Vendor\Models\Vendor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VendorPickerController extends Controller
{
    public function index(ListVendorsRequest $request, CurrentTenant $currentTenant): AnonymousResourceCollection
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        abort_unless(in_array($currentTenant->roleFor($request->user()), [TenantRole::Buyer->value, TenantRole::Admin->value], true), 403);

        $query = Vendor::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', $request->validated('status') ?? 'active')
            ->when($request->validated('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('name')
            ->limit(50);

        return VendorPickerResource::collection($query->get());
    }

    private function tenantOrAbort(CurrentTenant $currentTenant)
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
