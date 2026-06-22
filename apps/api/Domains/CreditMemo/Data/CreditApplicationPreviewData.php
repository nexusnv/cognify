<?php

namespace Domains\CreditMemo\Data;

readonly class CreditApplicationPreviewData
{
    public function __construct(
        public string $creditMemoId,
        public string $invoiceId,
        public string $appliedAmount,
        public string $applicationDate,
        public string $creditMemoRemaining,
        public string $invoiceOutstanding,
        public ?string $notes = null,
    ) {}
}
