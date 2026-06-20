<?php

namespace Tests\Feature;

use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionSeverity;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionType;
use Tests\TestCase;

class SupplierCreditMemoExceptionEnumTest extends TestCase
{
    public function test_severity_cases(): void
    {
        $this->assertSame('blocking', SupplierCreditMemoExceptionSeverity::Blocking->value);
        $this->assertSame('warning', SupplierCreditMemoExceptionSeverity::Warning->value);
        $this->assertSame('info', SupplierCreditMemoExceptionSeverity::Info->value);
    }

    public function test_resolution_type_cases(): void
    {
        $this->assertSame('accepted', SupplierCreditMemoExceptionResolutionType::Accepted->value);
        $this->assertSame('value_adjustment', SupplierCreditMemoExceptionResolutionType::ValueAdjustment->value);
        $this->assertSame('info_only', SupplierCreditMemoExceptionResolutionType::InfoOnly->value);
    }

    public function test_exception_type_cases(): void
    {
        $this->assertSame('tax_code_mismatch', SupplierCreditMemoExceptionType::TaxCodeMismatch->value);
        $this->assertSame('currency_mismatch', SupplierCreditMemoExceptionType::CurrencyMismatch->value);
        $this->assertSame('math_error', SupplierCreditMemoExceptionType::MathError->value);
    }
}
