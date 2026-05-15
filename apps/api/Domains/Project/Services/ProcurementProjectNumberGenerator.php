<?php

namespace Domains\Project\Services;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Illuminate\Support\Facades\DB;

class ProcurementProjectNumberGenerator
{
    public function nextForTenant(Tenant $tenant): string
    {
        return DB::transaction(function () use ($tenant): string {
            $year = now()->year;
            $prefix = "PRJ-{$year}-";
            $latest = ProcurementProject::query()
                ->where('tenant_id', $tenant->id)
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $sequence = $latest ? ((int) substr((string) $latest, -6)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
