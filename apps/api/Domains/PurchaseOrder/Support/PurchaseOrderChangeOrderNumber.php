<?php

namespace Domains\PurchaseOrder\Support;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderChangeOrderNumber
{
    public function nextFor(PurchaseOrder $purchaseOrder): string
    {
        $count = DB::table('purchase_order_change_orders')
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->lockForUpdate()
            ->count();

        return $purchaseOrder->number.'-CO-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
