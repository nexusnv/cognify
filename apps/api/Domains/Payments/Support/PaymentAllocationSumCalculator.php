<?php

namespace Domains\Payments\Support;

use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentAllocation;

class PaymentAllocationSumCalculator
{
    public function sumForInvoice(SupplierInvoice $invoice): string
    {
        $result = ApPaymentAllocation::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('supplier_invoice_id', $invoice->id)
            ->whereNull('voided_at')
            ->sum('allocated_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function sumForHandoff(ApPaymentHandoff $handoff): string
    {
        $result = ApPaymentAllocation::query()
            ->where('tenant_id', $handoff->tenant_id)
            ->where('ap_payment_handoff_id', $handoff->id)
            ->whereNull('voided_at')
            ->sum('allocated_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function derivePaymentStatus(SupplierInvoice $invoice): SupplierInvoicePaymentStatus
    {
        $allocated = $this->sumForInvoice($invoice);
        $total = (string) $invoice->total_amount;

        if (bccomp($allocated, $total, 4) === 0) {
            return SupplierInvoicePaymentStatus::Paid;
        }

        if (bccomp($allocated, '0.0000', 4) === 1) {
            return SupplierInvoicePaymentStatus::PartiallyPaid;
        }

        return SupplierInvoicePaymentStatus::PaymentScheduled;
    }
}
