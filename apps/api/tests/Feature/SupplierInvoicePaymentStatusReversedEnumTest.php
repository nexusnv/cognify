<?php

namespace Tests\Feature;

use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Tests\TestCase;

class SupplierInvoicePaymentStatusReversedEnumTest extends TestCase
{
    public function test_reversed_case_exists(): void
    {
        $this->assertSame('reversed', SupplierInvoicePaymentStatus::Reversed->value);
    }

    public function test_reversed_label(): void
    {
        $this->assertSame('Reversed', SupplierInvoicePaymentStatus::Reversed->label());
    }

    public function test_reversed_is_terminal(): void
    {
        $this->assertTrue(SupplierInvoicePaymentStatus::Reversed->isTerminal());
        $this->assertTrue(SupplierInvoicePaymentStatus::Paid->isTerminal());
    }

    public function test_reversed_is_not_eligible_for_handoff(): void
    {
        $this->assertFalse(SupplierInvoicePaymentStatus::Reversed->isEligibleForHandoff());
    }

    public function test_can_apply_credit_from_returns_true_for_pre_states(): void
    {
        $this->assertTrue(SupplierInvoicePaymentStatus::PaymentEligible->canApplyCreditFrom());
        $this->assertTrue(SupplierInvoicePaymentStatus::PaymentReady->canApplyCreditFrom());
        $this->assertTrue(SupplierInvoicePaymentStatus::PartiallyPaid->canApplyCreditFrom());
        $this->assertTrue(SupplierInvoicePaymentStatus::Paid->canApplyCreditFrom());
    }

    public function test_can_apply_credit_from_returns_false_for_blocked_states(): void
    {
        $this->assertFalse(SupplierInvoicePaymentStatus::OnHold->canApplyCreditFrom());
        $this->assertFalse(SupplierInvoicePaymentStatus::PaymentScheduled->canApplyCreditFrom());
        $this->assertFalse(SupplierInvoicePaymentStatus::HandoffExported->canApplyCreditFrom());
        $this->assertFalse(SupplierInvoicePaymentStatus::Reversed->canApplyCreditFrom());
    }
}
