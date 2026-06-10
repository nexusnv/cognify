<?php

namespace Domains\Receiving\Support;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class ReceivingNumber
{
    public static function nextFor(PurchaseOrder $purchaseOrder): string
    {
        $year = now()->format('Y');

        $sequence = DB::transaction(function () use ($purchaseOrder, $year): int {
            $row = DB::table('goods_receipts')
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->where('number', 'like', "GR-{$year}-%")
                ->lockForUpdate()
                ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(number, LENGTH(?) + 2) AS UNSIGNED)), 0) + 1 AS next_seq', ["GR-{$year}-"])
                ->first();

            return (int) $row->next_seq;
        });

        return sprintf('GR-%s-%06d', $year, $sequence);
    }
}
