<?php

namespace Domains\Invoice\Support;

use Domains\Invoice\Exceptions\DuplicateSupplierInvoiceException;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\PurchaseOrder\Models\PurchaseOrder;

class SupplierInvoiceDuplicateChecker
{
    public function ensureUniqueForPurchaseOrder(PurchaseOrder $purchaseOrder, string $invoiceNumber): void
    {
        $normalizedNumber = SupplierInvoiceNumber::normalize($invoiceNumber);

        $matchingInvoice = SupplierInvoice::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('invoice_number_normalized', $normalizedNumber)
            ->first(['id', 'number', 'invoice_number']);

        if ($matchingInvoice !== null) {
            throw new DuplicateSupplierInvoiceException([
                'id' => (string) $matchingInvoice->id,
                'number' => $matchingInvoice->number,
                'invoiceNumber' => $matchingInvoice->invoice_number,
            ]);
        }
    }
}
