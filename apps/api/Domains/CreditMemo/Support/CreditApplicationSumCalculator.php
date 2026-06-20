<?php

namespace Domains\CreditMemo\Support;

use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\Invoice\Models\SupplierInvoice;

class CreditApplicationSumCalculator
{
    public function sumForCreditMemo(SupplierCreditMemo $memo): string
    {
        $result = CreditApplication::query()
            ->where('supplier_credit_memo_id', $memo->id)
            ->whereNull('voided_at')
            ->sum('applied_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function sumForInvoice(SupplierInvoice $invoice): string
    {
        $result = CreditApplication::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->whereNull('voided_at')
            ->sum('applied_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function deriveCreditMemoStatus(SupplierCreditMemo $memo): SupplierCreditMemoStatus
    {
        $applied = $this->sumForCreditMemo($memo);
        $total = (string) $memo->total_amount;

        if (bccomp($applied, $total, 4) === 0 || bccomp($applied, $total, 4) === 1) {
            return SupplierCreditMemoStatus::FullyApplied;
        }

        if (bccomp($applied, '0.0000', 4) === 1) {
            return SupplierCreditMemoStatus::PartiallyApplied;
        }

        return SupplierCreditMemoStatus::Open;
    }

    public function deriveInvoicePaymentStatus(
        SupplierInvoice $invoice,
        SupplierInvoicePaymentStatus $priorStatus,
    ): SupplierInvoicePaymentStatus {
        $applied = $this->sumForInvoice($invoice);
        $total = (string) $invoice->total_amount;

        if (bccomp($applied, $total, 4) >= 0) {
            return SupplierInvoicePaymentStatus::Reversed;
        }

        return $priorStatus;
    }
}
