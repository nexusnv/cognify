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
            $sequence = DB::table('goods_receipt_sequences')
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                DB::table('goods_receipt_sequences')->insert([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'year' => $year,
                    'last_sequence' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $nextSeq = $sequence->last_sequence + 1;

            DB::table('goods_receipt_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_sequence' => $nextSeq,
                    'updated_at' => now(),
                ]);

            return $nextSeq;
        });

        return sprintf('GR-%s-%06d', $year, $sequence);
    }
}
