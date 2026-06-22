<?php

namespace Domains\CreditMemo\Data;

/**
 * @phpstan-type CreditMemoLinePayload array{
 *     line_number: int,
 *     description: string,
 *     quantity: string,
 *     unit_price: string,
 *     tax_code?: string|null,
 *     tax_amount?: string|null,
 *     purchase_order_line_id?: string|null,
 *     original_invoice_line_id?: string|null,
 *     notes?: string|null,
 * }
 */
readonly class SupplierCreditMemoContextData
{
    /**
     * @param  CreditMemoLinePayload[]  $lines
     */
    public function __construct(
        public int $vendorId,
        public ?string $originalInvoiceId,
        public string $vendorCreditMemoNumber,
        public ?string $creditDate,
        public string $currency,
        public string $subtotal,
        public string $tax,
        public string $freight,
        public string $total,
        public array $lines,
        public ?string $notes,
    ) {}
}
