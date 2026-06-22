<?php

namespace Domains\CreditMemo\Support;

use Illuminate\Support\Facades\DB;

class SupplierCreditMemoDuplicateDetector
{
    public function isDuplicate(
        int $tenantId,
        int $vendorId,
        string $originalInvoiceId,
        string $vendorCreditMemoNumber,
        ?string $excludeId = null,
    ): bool {
        return DB::table('supplier_credit_memos')
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->where('original_invoice_id', $originalInvoiceId)
            ->where('vendor_credit_memo_number', $vendorCreditMemoNumber)
            ->whereNull('voided_at')
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}
