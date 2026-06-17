<?php

namespace Domains\Invoice\Data;

final class InvoiceMatchResultData
{
    public function __construct(
        public readonly string $dimension,
        public readonly string $matchType,
        public readonly string $matchLevel,
        public readonly ?string $supplierInvoiceLineId,
        public readonly ?string $purchaseOrderLineId,
        public readonly ?string $expectedValue,
        public readonly ?string $actualValue,
        public readonly ?float $tolerancePercentApplied,
        public readonly ?float $toleranceFloorApplied,
        public readonly ?float $toleranceCapApplied,
        public readonly string $result,
        public readonly ?string $notes = null,
    ) {}
}
