<?php

namespace Domains\Payments\Data;

class PaymentImportPreviewData
{
    public function __construct(
        public readonly string $batchId,
        public readonly int $totalRows,
        public readonly array $rows,
    ) {}
}
