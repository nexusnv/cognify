<?php

namespace Domains\Requisition\Services;

use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;

class RequisitionNumberGenerator
{
    public function nextFor(Tenant $tenant): string
    {
        $year = now()->format('Y');

        $count = Requisition::query()
            ->where('tenant_id', $tenant->id)
            ->whereYear('created_at', (int) $year)
            ->count();

        return sprintf('REQ-%s-%06d', $year, $count + 1);
    }
}
