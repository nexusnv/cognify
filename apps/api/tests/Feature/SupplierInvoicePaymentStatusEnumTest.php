<?php

namespace Tests\Feature;

use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Tests\TestCase;

class SupplierInvoicePaymentStatusEnumTest extends TestCase
{
    public function test_payment_scheduled_case_exists(): void
    {
        $this->assertSame('payment_scheduled', SupplierInvoicePaymentStatus::PaymentScheduled->value);
    }

    public function test_partially_paid_case_exists(): void
    {
        $this->assertSame('partially_paid', SupplierInvoicePaymentStatus::PartiallyPaid->value);
    }

    public function test_paid_case_exists(): void
    {
        $this->assertSame('paid', SupplierInvoicePaymentStatus::Paid->value);
    }

    public function test_payment_failed_is_not_a_column_case(): void
    {
        $cases = array_map(fn ($c) => $c->value, SupplierInvoicePaymentStatus::cases());
        $this->assertNotContains('payment_failed', $cases);
        $this->assertNotContains('payment_voided', $cases);
    }
}
