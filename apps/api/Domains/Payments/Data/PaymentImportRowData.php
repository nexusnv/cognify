<?php

namespace Domains\Payments\Data;

class PaymentImportRowData
{
    public function __construct(
        public readonly ?string $handoffNumber,
        public readonly ?string $invoiceNumber,
        public readonly ?string $paymentReference,
        public readonly ?string $allocatedAmount,
        public readonly bool $markFull,
        public readonly ?string $settlementAmount,
        public readonly ?string $settlementCurrency,
        public readonly ?string $paidAt,
        public readonly ?string $settlementMethod,
        public readonly string $status,
        public readonly ?string $failureCode,
        public readonly ?string $failureReason,
        public readonly ?string $voidReason,
    ) {}
}
