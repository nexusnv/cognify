<?php

namespace Domains\Invoice\Listeners;

use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Receiving\Events\GoodsReceiptLinePosted;

class ReRunMatchingOnGoodsReceipt
{
    public function handle(GoodsReceiptLinePosted $event): void
    {
        $receiptLine = $event->goodsReceiptLine;
        $purchaseOrderId = $receiptLine->goodsReceipt->purchase_order_id;

        if ($purchaseOrderId === null) {
            return;
        }

        $pendingInvoices = SupplierInvoice::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->where(function ($query) {
                $query->whereNull('matching_status')
                    ->orWhere('matching_status', SupplierInvoiceStatus::Reviewed->value);
            })
            ->orWhere(function ($query) use ($purchaseOrderId) {
                $query->where('purchase_order_id', $purchaseOrderId)
                    ->where('matching_status', SupplierInvoiceStatus::Mismatch->value);
            })
            ->get();

        foreach ($pendingInvoices as $invoice) {
            try {
                $invoice->refresh();
                $systemUser = User::query()->where('email', 'system@cognify.local')->first();
                if ($systemUser === null) {
                    continue;
                }

                $action = app(\Domains\Invoice\Actions\RunInvoiceMatching::class);
                $action->handle($invoice, $systemUser, $invoice->lock_version, 'automatic');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
