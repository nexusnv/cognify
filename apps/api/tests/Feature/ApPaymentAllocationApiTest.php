<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Invoice\Support\SupplierInvoiceNumber;
use Domains\Payments\Actions\AddApPaymentAllocation;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\QuotationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ApPaymentAllocationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);
        app(CurrentTenant::class)->set($tenant);
        return [$tenant, $user];
    }

    private function createScheduledHandoffWithInvoice(Tenant $tenant, string $currency = 'USD', string $totalAmount = '1000.0000'): array
    {
        $invoice = $this->createApprovedInvoice($tenant, $currency, $totalAmount);

        $handoff = ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'APH-'.Str::random(8),
            'status' => ApPaymentHandoffStatus::Scheduled->value,
            'currency' => $currency,
            'total_amount' => $totalAmount,
            'lock_version' => 1,
        ]);

        $handoff->invoices()->attach($invoice->id, ['tenant_id' => $tenant->id]);

        return [$handoff->fresh(['invoices']), $invoice];
    }

    private function createApprovedInvoice(Tenant $tenant, string $currency = 'USD', string $totalAmount = '1000.0000'): SupplierInvoice
    {
        $buyer = User::factory()->create();
        $tenant->users()->attach($buyer->id, ['role' => TenantRole::Buyer->value]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Vendor '.Str::random(6),
            'status' => 'active',
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::random(6),
            'title' => 'Test procurement',
            'status' => 'draft',
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::random(6),
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $totalAmount,
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $totalAmount,
        ]);

        $quotation->forceFill(['current_version_id' => $version->id])->save();

        $recommendation = RfqAwardRecommendation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'recommended_vendor_id' => $vendor->id,
            'recommended_quotation_id' => $quotation->id,
            'recommended_quotation_version_id' => $version->id,
            'status' => 'approved',
            'rationale' => 'Best value.',
            'created_by_user_id' => $buyer->id,
            'updated_by_user_id' => $buyer->id,
        ]);

        $poHandoff = PurchaseOrderRequestHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'approval_instance_id' => null,
            'rfq_id' => $rfq->id,
            'requisition_id' => null,
            'project_id' => null,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'HANDOFF-'.Str::random(6),
            'status' => PurchaseOrderRequestHandoffStatus::Draft,
            'currency' => $currency,
            'total_amount' => $totalAmount,
            'requested_by_user_id' => $buyer->id,
            'source_snapshot' => [],
            'line_snapshot' => [],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'readiness_warnings' => [],
            'lock_version' => 1,
        ]);

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_request_handoff_id' => $poHandoff->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'PO-'.Str::random(8),
            'status' => 'issued',
            'currency' => $currency,
            'total_amount' => $totalAmount,
            'created_by_user_id' => $buyer->id,
            'source_snapshot' => [],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'lock_version' => 1,
        ]);

        $invoiceNumber = 'INV-'.Str::random(8);
        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'vendor_id' => $vendor->id,
            'number' => $invoiceNumber,
            'invoice_number' => $invoiceNumber,
            'invoice_number_normalized' => SupplierInvoiceNumber::normalize($invoiceNumber),
            'status' => SupplierInvoiceStatus::Approved->value,
            'invoice_date' => now()->toDateString(),
            'currency' => $currency,
            'subtotal_amount' => $totalAmount,
            'total_amount' => $totalAmount,
            'captured_by_user_id' => $buyer->id,
            'captured_at' => now(),
            'lock_version' => 1,
        ]);

        return $invoice->fresh();
    }

    public function test_full_allocation_marks_invoice_paid(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$handoff, $invoice] = $this->createScheduledHandoffWithInvoice($tenant);

        $allocation = app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '1000.0000',
            allocationDate: '2026-06-20',
        );

        $this->assertSame('1000.0000', (string) $allocation->allocated_amount);
        $this->assertNull($allocation->payment_reference);

        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::Paid, $invoice->payment_status);
        $this->assertSame(2, $invoice->lock_version);
    }

    public function test_partial_allocation_marks_invoice_partially_paid(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$handoff, $invoice] = $this->createScheduledHandoffWithInvoice($tenant);

        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '600.0000',
            allocationDate: '2026-06-20',
        );

        $invoice->refresh();
        $this->assertSame(SupplierInvoicePaymentStatus::PartiallyPaid, $invoice->payment_status);
    }

    public function test_over_allocation_throws_validation_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$handoff, $invoice] = $this->createScheduledHandoffWithInvoice($tenant, 'USD', '1000.0000');

        $this->expectException(ValidationException::class);

        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '1500.0000',
            allocationDate: '2026-06-20',
        );
    }

    public function test_zero_allocation_throws_validation_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$handoff, $invoice] = $this->createScheduledHandoffWithInvoice($tenant);

        $this->expectException(ValidationException::class);

        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '0.0000',
            allocationDate: '2026-06-20',
        );
    }

    public function test_allocation_to_non_scheduled_handoff_throws_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $invoice = $this->createApprovedInvoice($tenant);
        $handoff = ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'APH-'.Str::random(8),
            'status' => ApPaymentHandoffStatus::Exported->value,
            'currency' => 'USD',
            'total_amount' => '1000.0000',
            'lock_version' => 1,
        ]);
        $handoff->invoices()->attach($invoice->id, ['tenant_id' => $tenant->id]);

        $this->expectException(ConflictHttpException::class);

        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '500.0000',
            allocationDate: '2026-06-20',
        );
    }

    public function test_allocation_for_invoice_not_in_handoff_throws_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$handoff, $invoiceInHandoff] = $this->createScheduledHandoffWithInvoice($tenant);

        $otherInvoice = $this->createApprovedInvoice($tenant, 'USD', '500.0000');

        $this->expectException(ConflictHttpException::class);

        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $otherInvoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '100.0000',
            allocationDate: '2026-06-20',
        );
    }

    public function test_settlement_currency_differs_requires_settlement_amount(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$handoff, $invoice] = $this->createScheduledHandoffWithInvoice($tenant, 'EUR');

        $this->expectException(ValidationException::class);

        app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '1000.0000',
            allocationDate: '2026-06-20',
            settlementCurrency: 'USD',
        );
    }

    public function test_empty_string_payment_reference_normalized_to_null(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$handoff, $invoice] = $this->createScheduledHandoffWithInvoice($tenant);

        $allocation = app(AddApPaymentAllocation::class)->handle(
            $handoff,
            $invoice,
            $buyer,
            $handoff->lock_version,
            allocatedAmount: '1000.0000',
            allocationDate: '2026-06-20',
            paymentReference: '   ',
        );

        $this->assertNull($allocation->payment_reference);
    }
}
