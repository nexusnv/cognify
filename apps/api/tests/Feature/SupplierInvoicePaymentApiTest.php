<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Actions\EvaluatePaymentReadiness;
use Domains\AccountsPayable\Actions\HoldSupplierInvoicePayment;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Invoice\Support\SupplierInvoiceNumber;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierInvoicePaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_invoice_auto_advances_to_payment_eligible(): void
    {
        $invoice = $this->createApprovedInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $action = app(EvaluatePaymentReadiness::class);
        $action->handle($invoice, $buyer);

        $invoice->refresh();

        $this->assertEquals('payment_eligible', $invoice->payment_status?->value);
        $this->assertNotNull($invoice->payment_eligible_at);
    }

    public function test_ap_user_can_place_hold_with_reason(): void
    {
        $invoice = $this->createPaymentEligibleInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($invoice->tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/place-hold", [
                'reason' => 'Invoice under payment review.',
                'lockVersion' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.paymentStatus', 'on_hold');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => SupplierInvoicePaymentStatus::OnHold->value,
            'payment_on_hold_reason' => 'Invoice under payment review.',
        ]);
    }

    public function test_placing_hold_requires_lock_version(): void
    {
        $invoice = $this->createPaymentEligibleInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($invoice->tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/place-hold", [
                'reason' => 'Invoice under payment review.',
                'lockVersion' => 999,
            ])
            ->assertConflict();
    }

    public function test_ap_user_can_release_hold_with_note(): void
    {
        $invoice = $this->createOnHoldInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($invoice->tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/release-hold", [
                'releaseNote' => 'Payment hold released after review.',
                'lockVersion' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.paymentStatus', 'payment_eligible');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => SupplierInvoicePaymentStatus::PaymentEligible->value,
            'payment_hold_released_note' => 'Payment hold released after review.',
        ]);
    }

    public function test_retry_payment_induction_for_ghost_approved(): void
    {
        $invoice = $this->createApprovedInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($invoice->tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/retry-payment-induction", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.paymentStatus', 'payment_eligible');
    }

    public function test_retry_payment_induction_is_idempotent(): void
    {
        $invoice = $this->createPaymentEligibleInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $response1 = $this->actingAsTenant($invoice->tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/retry-payment-induction", [
                'lockVersion' => 2,
            ]);

        $response1->assertOk()
            ->assertJsonPath('data.paymentStatus', 'payment_eligible');

        $lockVersionAfterFirst = $response1->json('data.lockVersion');

        $response2 = $this->actingAsTenant($invoice->tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/retry-payment-induction", [
                'lockVersion' => $lockVersionAfterFirst,
            ]);

        $response2->assertOk()
            ->assertJsonPath('data.paymentStatus', 'payment_eligible')
            ->assertJsonPath('data.lockVersion', $lockVersionAfterFirst);
    }

    public function test_cross_tenant_hold_is_denied(): void
    {
        $invoice = $this->createPaymentEligibleInvoice();
        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/place-hold", [
                'reason' => 'Invoice under payment review.',
                'lockVersion' => 2,
            ])
            ->assertForbidden();
    }

    public function test_hold_audit_event_recorded(): void
    {
        $invoice = $this->createPaymentEligibleInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($invoice->tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/place-hold", [
                'reason' => 'Invoice under payment review.',
                'lockVersion' => 2,
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'action' => 'supplier_invoice.payment_hold',
        ]);
    }

    private function createApprovedInvoice(?Tenant $tenant = null): SupplierInvoice
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value, $tenant);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Vendor',
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
            'currency' => 'MYR',
            'total_amount' => '1000.00',
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '1000.00',
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

        $handoff = PurchaseOrderRequestHandoff::query()->create([
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
            'currency' => 'MYR',
            'total_amount' => '1000.00',
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
            'purchase_order_request_handoff_id' => $handoff->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'PO-'.Str::random(8),
            'status' => 'issued',
            'currency' => 'MYR',
            'total_amount' => '1000.00',
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
            'currency' => 'MYR',
            'subtotal_amount' => '1000.0000',
            'total_amount' => '1000.0000',
            'captured_by_user_id' => $buyer->id,
            'captured_at' => now(),
            'lock_version' => 1,
        ]);

        return $invoice->fresh();
    }

    private function createPaymentEligibleInvoice(?Tenant $tenant = null): SupplierInvoice
    {
        $invoice = $this->createApprovedInvoice($tenant);
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $action = app(EvaluatePaymentReadiness::class);
        $action->handle($invoice, $buyer);

        return $invoice->fresh();
    }

    private function createOnHoldInvoice(?Tenant $tenant = null): SupplierInvoice
    {
        $invoice = $this->createPaymentEligibleInvoice($tenant);
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $action = app(HoldSupplierInvoicePayment::class);
        $action->handle($invoice, $buyer, lockVersion: 2, reason: 'Payment put on hold.');

        return $invoice->fresh();
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function tenantUserPair(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }

    private function tenantUser(Tenant $tenant, string $role): User
    {
        [, $user] = $this->tenantUserPair($role, $tenant);

        return $user;
    }
}
