<?php

namespace Domains\Invoice\Listeners;

use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Receiving\Events\GoodsReceiptLinePosted;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReRunMatchingOnGoodsReceipt implements ShouldQueue
{
    public function handle(GoodsReceiptLinePosted $event): void
    {
        $receiptLine = $event->goodsReceiptLine;
        $purchaseOrderId = $receiptLine->goodsReceipt->purchase_order_id;

        if ($purchaseOrderId === null) {
            return;
        }

        // Fetch system user once, before the loop (prevents N+1 queries)
        $systemUser = User::query()->where('email', 'system@cognify.local')->first();
        if ($systemUser === null) {
            return;
        }

        // Only re-match invoices in Reviewed status (RunInvoiceMatching requirement)
        $pendingInvoices = SupplierInvoice::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', SupplierInvoiceStatus::Reviewed->value)
            ->where(function ($query) {
                // Match invoices with no matching_status OR that failed matching previously
                $query->whereNull('matching_status')
                    ->orWhere('matching_status', SupplierInvoiceStatus::Mismatch->value);
            })
            ->get();

        foreach ($pendingInvoices as $invoice) {
            try {
                $invoice->refresh();
                
                $action = app(\Domains\Invoice\Actions\RunInvoiceMatching::class);
                $action->handle($invoice, $systemUser, $invoice->lock_version, 'automatic');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}}
