<?php

namespace Domains\AccountsPayable\Support;

use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

class ApPaymentHandoffNumber
{
    public function generate(int $tenantId): string
    {
        $year = now()->format('Y');
        Tenant::query()->whereKey($tenantId)->lockForUpdate()->exists();
        $lastNumber = DB::table('ap_payment_handoffs')
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', "APH-{$year}-%")
            ->orderBy('number', 'desc')->value('number');
        if ($lastNumber === null) {
            return "APH-{$year}-000001";
        }
        $parts = explode('-', $lastNumber);
        $counter = (int) end($parts);

        return "APH-{$year}-".str_pad((string) ($counter + 1), 6, '0', STR_PAD_LEFT);
    }
}
