<?php

namespace Tests\Feature;

use Domains\Payments\States\ApPaymentFailureCode;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Tests\TestCase;

class ApPaymentEnumsTest extends TestCase
{
    public function test_failure_code_cases(): void
    {
        $this->assertSame('bank_rejected', ApPaymentFailureCode::BankRejected->value);
        $this->assertSame('other', ApPaymentFailureCode::Other->value);
    }

    public function test_import_status_cases(): void
    {
        $this->assertSame('pending', ApPaymentImportStatus::Pending->value);
        $this->assertSame('reconciled', ApPaymentImportStatus::Reconciled->value);
    }

    public function test_import_target_status_cases(): void
    {
        $this->assertSame('paid', ApPaymentImportTargetStatus::Paid->value);
        $this->assertSame('voided', ApPaymentImportTargetStatus::Voided->value);
    }
}
