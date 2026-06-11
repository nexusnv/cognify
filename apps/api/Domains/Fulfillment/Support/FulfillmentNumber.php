<?php

namespace Domains\Fulfillment\Support;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class FulfillmentNumber
{
    public static function nextFor(PurchaseOrder $purchaseOrder): string
    {
        $year = now()->format('Y');

        $latestNumber = DB::table('shipments')
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('number', 'like', "SH-{$year}-%")
            ->lockForUpdate()
            ->max('number');

        $nextSequence = 1;

        if (is_string($latestNumber) && preg_match('/^SH-\d{4}-(\d{6})$/', $latestNumber, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('SH-%s-%06d', $year, $nextSequence);
    }
}
