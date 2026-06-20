<?php

namespace Domains\CreditMemo\Data;

use Domains\CreditMemo\States\SupplierCreditMemoExceptionSeverity;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionType;

readonly class SupplierCreditMemoExceptionData
{
    public function __construct(
        public SupplierCreditMemoExceptionType $exceptionType,
        public SupplierCreditMemoExceptionSeverity $severity,
        public string $description,
        public ?string $expectedValue = null,
        public ?string $adjustedValue = null,
    ) {}
}
