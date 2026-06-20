<?php

namespace Domains\Payments\Support;

use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentAllocation;

class PaymentAllocationSumCalculator
{
    public function sumForInvoice(SupplierInvoice $invoice): string
    {
        $result = ApPaymentAllocation::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->whereNull('voided_at')
            ->sum('allocated_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function sumForHandoff(string $handoffId): string
    {
        $result = ApPaymentAllocation::query()
            ->where('ap_payment_handoff_id', $handoffId)
            ->whereNull('voided_at')
            ->sum('allocated_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function derivePaymentStatus(SupplierInvoice $invoice): \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus
    {
        $allocated = $this->sumForInvoice($invoice);
        $total = (string) $invoice->total_amount;

        if (bccomp($allocated, $total, 4) === 0) {
            return \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus::Paid;
        }

        if (bccomp($allocated, '0.0000', 4) === 1) {
            return \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus::PartiallyPaid;
        }

        return \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus::PaymentScheduled;
    }
}
