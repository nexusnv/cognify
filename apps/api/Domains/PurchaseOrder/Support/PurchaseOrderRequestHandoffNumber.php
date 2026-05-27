<?php

namespace Domains\PurchaseOrder\Support;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;

class PurchaseOrderRequestHandoffNumber
{
    public static function next(Tenant $tenant): string
    {
        Tenant::query()
            ->whereKey($tenant->id)
            ->lockForUpdate()
            ->firstOrFail();

        $prefix = 'POH-'.now()->format('Y').'-';

        $latest = PurchaseOrderRequestHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $next = $latest !== null
            ? ((int) substr((string) $latest, -6)) + 1
            : 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
