<?php

namespace Tests\Feature;

use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Tests\TestCase;

class ApPaymentHandoffStatusEnumTest extends TestCase
{
    public function test_p1_48_cases_exist(): void
    {
        $this->assertSame('scheduled', ApPaymentHandoffStatus::Scheduled->value);
        $this->assertSame('paid', ApPaymentHandoffStatus::Paid->value);
        $this->assertSame('failed', ApPaymentHandoffStatus::Failed->value);
        $this->assertSame('voided', ApPaymentHandoffStatus::Voided->value);
    }

    public function test_transition_table(): void
    {
        $this->assertTrue(ApPaymentHandoffStatus::Exported->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
        $this->assertTrue(ApPaymentHandoffStatus::Scheduled->canTransitionTo(ApPaymentHandoffStatus::Paid));
        $this->assertTrue(ApPaymentHandoffStatus::Scheduled->canTransitionTo(ApPaymentHandoffStatus::Failed));
        $this->assertTrue(ApPaymentHandoffStatus::Scheduled->canTransitionTo(ApPaymentHandoffStatus::Voided));
        $this->assertTrue(ApPaymentHandoffStatus::Paid->canTransitionTo(ApPaymentHandoffStatus::Voided));
        $this->assertTrue(ApPaymentHandoffStatus::Failed->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
        $this->assertTrue(ApPaymentHandoffStatus::Failed->canTransitionTo(ApPaymentHandoffStatus::Voided));
        $this->assertFalse(ApPaymentHandoffStatus::Voided->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
        $this->assertFalse(ApPaymentHandoffStatus::Cancelled->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
    }
}
