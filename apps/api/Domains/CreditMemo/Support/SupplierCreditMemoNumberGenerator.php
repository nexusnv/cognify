<?php

namespace Domains\CreditMemo\Support;

use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

class SupplierCreditMemoNumberGenerator
{
    public function generate(int $tenantId): string
    {
        $year = now()->format('Y');

        Tenant::query()->whereKey($tenantId)->lockForUpdate()->exists();

        $lastNumber = DB::table('supplier_credit_memos')
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', "CM-{$year}-%")
            ->orderBy('number', 'desc')
            ->value('number');

        if ($lastNumber === null) {
            return "CM-{$year}-000001";
        }

        $parts = explode('-', $lastNumber);
        $counter = (int) end($parts);

        return "CM-{$year}-" . str_pad((string) ($counter + 1), 6, '0', STR_PAD_LEFT);
    }
}
