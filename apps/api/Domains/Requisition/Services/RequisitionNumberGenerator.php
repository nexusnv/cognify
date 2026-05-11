<?php

namespace Domains\Requisition\Services;

use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

class RequisitionNumberGenerator
{
    public function nextFor(Tenant $tenant): string
    {
        return DB::transaction(function () use ($tenant): string {
            $year = (int) now()->format('Y');
            $now = now();

            DB::table('requisition_sequences')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'year' => $year,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DB::table('requisition_sequences')
                ->where('tenant_id', $tenant->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('requisition_sequences')
                ->where('tenant_id', $tenant->id)
                ->where('year', $year)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => $now,
                ]);

            return sprintf('REQ-%d-%06d', $year, $nextNumber);
        });
    }
}
