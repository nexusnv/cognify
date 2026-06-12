<?php

namespace Domains\Invoice\Support;

use Domains\Invoice\Models\SupplierInvoice;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierInvoiceDuplicateChecker
{
    public function ensureUniqueForPurchaseOrder(PurchaseOrder $purchaseOrder, string $invoiceNumber): void
    {
        $normalizedNumber = SupplierInvoiceNumber::normalize($invoiceNumber);

        $exists = SupplierInvoice::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('invoice_number_normalized', $normalizedNumber)
            ->exists();

        if ($exists) {
            throw new ConflictHttpException('A supplier invoice with this number already exists for the purchase order.');
        }
    }
}
