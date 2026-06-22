<?php

namespace Tests\Feature;

use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Tests\TestCase;

class SupplierCreditMemoStatusEnumTest extends TestCase
{
    public function test_all_status_cases_exist(): void
    {
        $this->assertSame('draft', SupplierCreditMemoStatus::Draft->value);
        $this->assertSame('pending_approval', SupplierCreditMemoStatus::PendingApproval->value);
        $this->assertSame('approved', SupplierCreditMemoStatus::Approved->value);
        $this->assertSame('open', SupplierCreditMemoStatus::Open->value);
        $this->assertSame('partially_applied', SupplierCreditMemoStatus::PartiallyApplied->value);
        $this->assertSame('fully_applied', SupplierCreditMemoStatus::FullyApplied->value);
        $this->assertSame('closed', SupplierCreditMemoStatus::Closed->value);
        $this->assertSame('voided', SupplierCreditMemoStatus::Voided->value);
    }

    public function test_terminal_states(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Closed->isTerminal());
        $this->assertTrue(SupplierCreditMemoStatus::Voided->isTerminal());
        $this->assertFalse(SupplierCreditMemoStatus::Draft->isTerminal());
        $this->assertFalse(SupplierCreditMemoStatus::Open->isTerminal());
    }

    public function test_can_transition_to_draft(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Draft->canTransitionTo(SupplierCreditMemoStatus::PendingApproval));
        $this->assertTrue(SupplierCreditMemoStatus::Draft->canTransitionTo(SupplierCreditMemoStatus::Voided));
        $this->assertFalse(SupplierCreditMemoStatus::Draft->canTransitionTo(SupplierCreditMemoStatus::Open));
    }

    public function test_can_transition_to_pending_approval(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::PendingApproval->canTransitionTo(SupplierCreditMemoStatus::Approved));
        $this->assertTrue(SupplierCreditMemoStatus::PendingApproval->canTransitionTo(SupplierCreditMemoStatus::Draft));
        $this->assertTrue(SupplierCreditMemoStatus::PendingApproval->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_can_transition_to_approved(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Approved->canTransitionTo(SupplierCreditMemoStatus::Open));
        $this->assertTrue(SupplierCreditMemoStatus::Approved->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_can_transition_to_open(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Open->canTransitionTo(SupplierCreditMemoStatus::PartiallyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::Open->canTransitionTo(SupplierCreditMemoStatus::FullyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::Open->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_can_transition_to_partially_applied(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::PartiallyApplied->canTransitionTo(SupplierCreditMemoStatus::PartiallyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::PartiallyApplied->canTransitionTo(SupplierCreditMemoStatus::FullyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::PartiallyApplied->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_fully_applied_auto_transitions_to_closed(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::FullyApplied->canTransitionTo(SupplierCreditMemoStatus::Closed));
        $this->assertFalse(SupplierCreditMemoStatus::FullyApplied->canTransitionTo(SupplierCreditMemoStatus::Open));
    }

    public function test_terminal_states_have_no_outgoing_transitions(): void
    {
        $this->assertFalse(SupplierCreditMemoStatus::Closed->canTransitionTo(SupplierCreditMemoStatus::Open));
        $this->assertFalse(SupplierCreditMemoStatus::Closed->canTransitionTo(SupplierCreditMemoStatus::Voided));
        $this->assertFalse(SupplierCreditMemoStatus::Voided->canTransitionTo(SupplierCreditMemoStatus::Draft));
    }

    public function test_can_accept_credit_applications(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Open->canAcceptCreditApplications());
        $this->assertTrue(SupplierCreditMemoStatus::PartiallyApplied->canAcceptCreditApplications());
        $this->assertFalse(SupplierCreditMemoStatus::Draft->canAcceptCreditApplications());
        $this->assertFalse(SupplierCreditMemoStatus::Closed->canAcceptCreditApplications());
        $this->assertFalse(SupplierCreditMemoStatus::Voided->canAcceptCreditApplications());
    }
}
