<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\Models\ApPaymentHandoffInvoice;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Payments\Actions\AddApPaymentAllocation;
use Domains\Payments\Actions\CloseApPaymentHandoffWithVariance;
use Domains\Payments\Actions\MarkApPaymentHandoffFailed;
use Domains\Payments\Actions\MarkApPaymentHandoffPaid;
use Domains\Payments\Actions\RescheduleFailedApPaymentHandoff;
use Domains\Payments\Actions\ScheduleApPaymentHandoff;
use Domains\Payments\Actions\VoidApPaymentHandoff;
use Domains\Payments\States\ApPaymentFailureCode;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ApPaymentStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);
        app(CurrentTenant::class)->set($tenant);

        return [$tenant, $user];
    }

    private function createExportedHandoff(Tenant $tenant, User $buyer): ApPaymentHandoff
    {
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Vendor '.Str::random(6),
            'status' => 'active',
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::random(6),
            'title' => 'Test procurement',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Test.',
            'line_items' => [],
        ]);

        $recommendation = RfqAwardRecommendation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'recommended_vendor_id' => $vendor->id,
            'status' => RfqAwardRecommendationStatus::Approved->value,
            'rationale' => 'Best price.',
            'created_by_user_id' => $buyer->id,
            'updated_by_user_id' => $buyer->id,
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => 'submitted',
            'currency' => 'USD',
            'total_amount' => '1000.00',
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'manual_entry_complete' => true,
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => 'submitted',
            'currency' => 'USD',
            'total_amount' => '1000.00',
        ]);

        $handoff = PurchaseOrderRequestHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'POH-'.Str::random(8),
            'status' => PurchaseOrderRequestHandoffStatus::Ready->value,
            'currency' => 'USD',
            'total_amount' => '1000.00',
            'requested_by_user_id' => $buyer->id,
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
            'source_snapshot' => [],
            'line_snapshot' => [],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'readiness_warnings' => [],
            'lock_version' => 1,
        ]);

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_request_handoff_id' => $handoff->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'PO-'.Str::random(8),
            'status' => 'issued',
            'currency' => 'USD',
            'total_amount' => '1000.00',
            'source_snapshot' => [],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'lock_version' => 1,
        ]);

        $invNumber = 'INV-'.Str::random(8);

        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'vendor_id' => $vendor->id,
            'number' => $invNumber,
            'invoice_number' => $invNumber,
            'invoice_number_normalized' => str_replace('-', '', $invNumber),
            'status' => SupplierInvoiceStatus::Approved->value,
            'invoice_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal_amount' => '1000.0000',
            'total_amount' => '1000.0000',
            'captured_by_user_id' => $buyer->id,
            'captured_at' => now(),
            'payment_status' => SupplierInvoicePaymentStatus::HandoffExported->value,
            'lock_version' => 1,
        ]);

        $apHandoff = ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'APH-'.Str::random(8),
            'status' => ApPaymentHandoffStatus::Exported->value,
            'currency' => 'USD',
            'total_amount' => '1000.0000',
            'lock_version' => 1,
        ]);

        ApPaymentHandoffInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'ap_payment_handoff_id' => $apHandoff->id,
            'supplier_invoice_id' => $invoice->id,
        ]);

        return $apHandoff->fresh(['invoices']);
    }

    public function test_exported_handoff_can_be_scheduled_via_action(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();

        $result = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            scheduledForDate: '2026-06-20',
            paymentReference: 'PRN-001',
        );

        $this->assertSame(ApPaymentHandoffStatus::Scheduled, $result->statusState());
        $this->assertNotNull($result->scheduled_at);
        $this->assertSame('2026-06-20', $result->scheduled_for_date?->toDateString());
        $this->assertSame('PRN-001', $result->payment_reference);
        $this->assertSame(2, $result->lock_version);

        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::PaymentScheduled, $invoice->payment_status);
        $this->assertSame(2, $invoice->lock_version);
    }

    public function test_scheduling_via_action_with_stale_lock_version_throws_conflict(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);

        $this->expectException(ConflictHttpException::class);

        app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            lockVersion: 999,
        );
    }

    public function test_scheduling_non_exported_handoff_via_action_throws_conflict(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();

        $handoff = ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'APH-'.Str::random(8),
            'status' => ApPaymentHandoffStatus::Draft->value,
            'currency' => 'USD',
            'total_amount' => '1000.0000',
            'lock_version' => 1,
        ]);

        $this->expectException(ConflictHttpException::class);

        app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );
    }

    public function test_fully_allocated_scheduled_handoff_can_be_marked_paid(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        $handoff->refresh();
        $invoice->refresh();
        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '1000.0000',
            allocationDate: '2026-06-20',
        );

        $handoff->refresh();
        $result = app(MarkApPaymentHandoffPaid::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            remittanceReference: 'REM-2026-001',
        );

        $this->assertSame(ApPaymentHandoffStatus::Paid, $result->statusState());
        $this->assertNotNull($result->paid_at);
        $this->assertSame('REM-2026-001', $result->remittance_reference);

        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::Paid, $invoice->payment_status);
    }

    public function test_under_allocated_handoff_cannot_be_marked_paid(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        $handoff->refresh();
        $invoice->refresh();
        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '600.0000',
            allocationDate: '2026-06-20',
        );

        $handoff->refresh();
        $this->expectException(ConflictHttpException::class);

        app(MarkApPaymentHandoffPaid::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );
    }

    public function test_non_scheduled_handoff_cannot_be_marked_paid(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);

        $this->expectException(ConflictHttpException::class);

        app(MarkApPaymentHandoffPaid::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );
    }

    public function test_short_pay_handoff_can_be_closed_with_variance(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();

        // Schedule the handoff
        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        // Allocate only 600 of 1000 (short-pay 400)
        $handoff->refresh();
        $invoice->refresh();
        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '600.0000',
            allocationDate: '2026-06-20',
        );

        // Close with variance
        $handoff->refresh();
        $result = app(CloseApPaymentHandoffWithVariance::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            varianceReason: 'Bank deducted wire fee',
        );

        $this->assertSame(ApPaymentHandoffStatus::Paid, $result->statusState());
        $this->assertSame('400.0000', (string) $result->variance_amount);
        $this->assertSame('Bank deducted wire fee', $result->variance_reason);
        $this->assertNotNull($result->variance_closed_at);
        $this->assertNotNull($result->paid_at);

        // Partially-allocated invoice stays PartiallyPaid (NOT Paid)
        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::PartiallyPaid, $invoice->payment_status);
    }

    public function test_close_with_variance_without_reason_throws_validation(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        $handoff->refresh();
        $invoice->refresh();
        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '100.0000',
            allocationDate: '2026-06-20',
        );

        $this->expectException(ValidationException::class);

        $handoff->refresh();
        app(CloseApPaymentHandoffWithVariance::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            varianceReason: 'ab', // Too short
        );
    }

    public function test_close_with_variance_without_any_allocations_throws_conflict(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );
        // No allocations added

        $this->expectException(ConflictHttpException::class);

        $handoff->refresh();
        app(CloseApPaymentHandoffWithVariance::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            varianceReason: 'Bank deducted wire fee',
        );
    }

    public function test_scheduled_handoff_can_be_marked_failed(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        $handoff->refresh();
        $result = app(MarkApPaymentHandoffFailed::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            failureCode: ApPaymentFailureCode::BankRejected,
            failureReason: 'Bank rejected wire',
        );

        $this->assertSame(ApPaymentHandoffStatus::Failed, $result->statusState());
        $this->assertSame('bank_rejected', $result->failure_code);
        $this->assertSame('Bank rejected wire', $result->failure_reason);
        $this->assertNotNull($result->failed_at);

        // CRITICAL INVARIANT: invoice column goes DIRECTLY to handoff_exported,
        // NEVER holds payment_failed. The payment_failed event is captured in
        // the audit event payload only.
        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::HandoffExported, $invoice->payment_status);

        // Database-level assertions: confirm the column value is handoff_exported and NOT payment_failed
        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => 'handoff_exported',
        ]);
        $this->assertDatabaseMissing('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => 'payment_failed',
        ]);
    }

    public function test_fail_without_reason_throws_validation(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        $this->expectException(ValidationException::class);

        $handoff->refresh();
        app(MarkApPaymentHandoffFailed::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            failureCode: ApPaymentFailureCode::Other,
            failureReason: 'err', // Too short
        );
    }

    public function test_fail_non_scheduled_handoff_throws_conflict(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        // handoff is Exported, not Scheduled

        $this->expectException(ConflictHttpException::class);

        app(MarkApPaymentHandoffFailed::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            failureCode: ApPaymentFailureCode::Other,
            failureReason: 'Other reason',
        );
    }

    public function test_scheduled_handoff_can_be_voided_releasing_invoices_to_handoff_exported(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        $handoff->refresh();
        $result = app(VoidApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            voidReason: 'Bank rejected the wire',
        );

        $this->assertSame(ApPaymentHandoffStatus::Voided, $result->statusState());
        $this->assertSame('Bank rejected the wire', $result->void_reason);
        $this->assertNotNull($result->voided_at);

        // CRITICAL INVARIANT: invoice column goes DIRECTLY to handoff_exported,
        // NEVER holds payment_voided. The payment_voided event is captured in
        // the audit event payload only.
        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::HandoffExported, $invoice->payment_status);

        // Database-level assertion: column is handoff_exported, NOT payment_voided
        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => 'handoff_exported',
        ]);
        $this->assertDatabaseMissing('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => 'payment_voided',
        ]);
    }

    public function test_void_without_reason_throws_validation(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);

        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );

        $this->expectException(ValidationException::class);

        $handoff->refresh();
        app(VoidApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            voidReason: 'no', // Too short
        );
    }

    public function test_failed_handoff_can_be_rescheduled(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        $invoice = $handoff->invoices->first();
        $handoff = app(ScheduleApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );
        $handoff->refresh();
        $handoff = app(MarkApPaymentHandoffFailed::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            failureCode: ApPaymentFailureCode::BankRejected,
            failureReason: 'Bank rejected wire',
        );
        $this->assertSame(ApPaymentHandoffStatus::Failed, $handoff->statusState());

        // Now reschedule
        $handoff->refresh();
        $result = app(RescheduleFailedApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
            scheduledForDate: '2026-06-25',
        );

        $this->assertSame(ApPaymentHandoffStatus::Scheduled, $result->statusState());
        $this->assertNull($result->failed_by_user_id);
        $this->assertNull($result->failed_at);
        $this->assertNull($result->failure_code);
        $this->assertNull($result->failure_reason);
        $this->assertSame('2026-06-25', $result->scheduled_for_date?->toDateString());

        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::PaymentScheduled, $invoice->payment_status);
    }

    public function test_non_failed_handoff_cannot_be_rescheduled(): void
    {
        [, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $tenant = app(CurrentTenant::class)->get();
        $handoff = $this->createExportedHandoff($tenant, $buyer);
        // handoff is Exported, not Failed

        $this->expectException(ConflictHttpException::class);

        app(RescheduleFailedApPaymentHandoff::class)->handle(
            $handoff,
            $buyer,
            $handoff->lock_version,
        );
    }
}
