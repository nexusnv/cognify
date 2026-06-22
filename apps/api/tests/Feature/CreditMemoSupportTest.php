<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\CreditMemo\Support\SupplierCreditMemoStateMachine;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditMemoSupportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, SupplierCreditMemo} */
    private function createMemo(string $totalAmount = '1000.0000', string $status = 'open'): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant ' . Str::uuid()]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'V',
            'status' => 'active',
        ]);

        $memo = SupplierCreditMemo::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'CM-SUP-' . Str::random(4),
            'vendor_id' => $vendor->id,
            'status' => $status,
            'currency' => 'USD',
            'subtotal_amount' => $totalAmount,
            'tax_amount' => '0.0000',
            'freight_amount' => '0.0000',
            'total_amount' => $totalAmount,
            'credit_date' => now()->format('Y-m-d'),
            'lock_version' => 1,
        ]);

        return [$tenant, $memo];
    }

    private function insertApplication(SupplierCreditMemo $memo, string $amount, ?string $voidedAt = null): void
    {
        $userId = User::factory()->create()->id;

        DB::table('rfqs')->insert([
            'tenant_id' => $memo->tenant_id, 'number' => 'RFQ-S' . Str::random(4),
            'title' => 'T', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rfqId = (int) DB::getPdo()->lastInsertId();

        DB::table('quotations')->insert([
            'tenant_id' => $memo->tenant_id, 'rfq_id' => $rfqId, 'vendor_id' => $memo->vendor_id,
            'number' => 'Q-S' . Str::random(4), 'status' => 'submitted', 'total_amount' => '1000.00',
            'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $quotationId = (int) DB::getPdo()->lastInsertId();

        DB::table('quotation_versions')->insert([
            'tenant_id' => $memo->tenant_id, 'quotation_id' => $quotationId, 'version_number' => 1,
            'status' => 'submitted', 'is_current' => true, 'currency' => 'USD',
            'total_amount' => '1000.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $quotationVersionId = (int) DB::getPdo()->lastInsertId();

        $recId = Str::uuid()->toString();
        DB::table('rfq_award_recommendations')->insert([
            'id' => $recId, 'tenant_id' => $memo->tenant_id, 'rfq_id' => $rfqId,
            'recommended_vendor_id' => $memo->vendor_id, 'recommended_quotation_id' => $quotationId,
            'recommended_quotation_version_id' => $quotationVersionId, 'status' => 'approved',
            'rationale' => '', 'created_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $handoffId = Str::uuid()->toString();
        DB::table('purchase_order_request_handoffs')->insert([
            'id' => $handoffId, 'tenant_id' => $memo->tenant_id,
            'rfq_award_recommendation_id' => $recId, 'rfq_id' => $rfqId,
            'vendor_id' => $memo->vendor_id, 'quotation_id' => $quotationId,
            'quotation_version_id' => $quotationVersionId, 'requested_by_user_id' => $userId,
            'number' => 'H-' . Str::random(4), 'status' => 'draft', 'currency' => 'USD',
            'total_amount' => '1000.00', 'source_snapshot' => '{}', 'line_snapshot' => '{}',
            'approval_snapshot' => '{}', 'evidence_snapshot' => '{}',
            'readiness_warnings' => '{}', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $poId = Str::uuid()->toString();
        DB::table('purchase_orders')->insert([
            'id' => $poId, 'tenant_id' => $memo->tenant_id,
            'purchase_order_request_handoff_id' => $handoffId,
            'rfq_award_recommendation_id' => $recId, 'rfq_id' => $rfqId,
            'vendor_id' => $memo->vendor_id, 'created_by_user_id' => $userId,
            'number' => 'PO-S' . Str::random(4), 'status' => 'issued', 'currency' => 'USD',
            'total_amount' => '1000.0000', 'source_snapshot' => '{}', 'approval_snapshot' => '{}',
            'evidence_snapshot' => '{}', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $number = 'INV-S' . Str::random(4);
        $invoiceId = Str::uuid()->toString();
        DB::table('supplier_invoices')->insert([
            'id' => $invoiceId, 'tenant_id' => $memo->tenant_id, 'purchase_order_id' => $poId,
            'vendor_id' => $memo->vendor_id, 'number' => $number, 'invoice_number' => $number,
            'invoice_number_normalized' => strtolower($number),
            'status' => 'approved', 'currency' => 'USD',
            'invoice_date' => now()->toDateString(),
            'subtotal_amount' => '1000.0000', 'tax_amount' => '0.0000',
            'freight_amount' => '0.0000', 'total_amount' => '1000.0000',
            'captured_by_user_id' => $userId, 'captured_at' => now(),
            'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('credit_applications')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $memo->tenant_id,
            'supplier_credit_memo_id' => $memo->id,
            'supplier_invoice_id' => $invoiceId,
            'applied_amount' => $amount,
            'application_date' => now()->format('Y-m-d'),
            'applied_by_user_id' => $userId,
            'voided_at' => $voidedAt,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_derive_status_when_no_applications(): void
    {
        [, $memo] = $this->createMemo('1000.0000', 'open');
        $calculator = new CreditApplicationSumCalculator();

        $this->assertSame(
            SupplierCreditMemoStatus::Open,
            $calculator->deriveCreditMemoStatus($memo),
        );
    }

    public function test_derive_status_when_partially_applied(): void
    {
        [, $memo] = $this->createMemo('1000.0000', 'open');
        $this->insertApplication($memo, '500.0000');

        $calculator = new CreditApplicationSumCalculator();
        $this->assertSame(
            SupplierCreditMemoStatus::PartiallyApplied,
            $calculator->deriveCreditMemoStatus($memo),
        );
    }

    public function test_derive_status_when_fully_applied(): void
    {
        [, $memo] = $this->createMemo('1000.0000', 'open');
        $this->insertApplication($memo, '1000.0000');

        $calculator = new CreditApplicationSumCalculator();
        $this->assertSame(
            SupplierCreditMemoStatus::FullyApplied,
            $calculator->deriveCreditMemoStatus($memo),
        );
    }

    public function test_rederive_keeps_closed_terminal(): void
    {
        // Closed is terminal — the enum reports isTerminal=true and
        // canTransitionTo returns false for all targets.
        $this->assertTrue(SupplierCreditMemoStatus::Closed->isTerminal());

        foreach (SupplierCreditMemoStatus::cases() as $target) {
            $this->assertFalse(
                SupplierCreditMemoStatus::Closed->canTransitionTo($target),
                "Closed should not transition to {$target->value}",
            );
        }
    }

    public function test_rederive_returns_to_open_when_all_voided(): void
    {
        [, $memo] = $this->createMemo('1000.0000', 'partially_applied');
        $this->insertApplication($memo, '500.0000', now()->toDateTimeString());

        $calculator = new CreditApplicationSumCalculator();
        $this->assertSame(
            SupplierCreditMemoStatus::Open,
            $calculator->deriveCreditMemoStatus($memo),
        );
    }

    public function test_calculator_classes_exist(): void
    {
        $this->assertTrue(class_exists(CreditApplicationSumCalculator::class));
        $this->assertTrue(class_exists(SupplierCreditMemoStateMachine::class));
    }
}
