<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Payments\Models\ApPaymentAllocation;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\Policies\ApPaymentAllocationPolicy;
use Domains\Payments\Policies\ApPaymentImportPolicy;
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
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_policy_buyer_can_view_and_create(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $allocation = $this->createAllocation($tenant);
        app(CurrentTenant::class)->set($tenant);

        $policy = app(ApPaymentAllocationPolicy::class);
        $this->assertTrue($policy->view($buyer, $allocation));
        $this->assertTrue($policy->create($buyer));
    }

    public function test_allocation_policy_rejects_requester(): void
    {
        [$tenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);
        $allocation = $this->createAllocation($tenant);
        app(CurrentTenant::class)->set($tenant);

        $policy = app(ApPaymentAllocationPolicy::class);
        $this->assertFalse($policy->view($requester, $allocation));
    }

    public function test_import_policy_buyer_can_upload_view_reconcile_discard(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $import = $this->createImport($tenant, $buyer);
        app(CurrentTenant::class)->set($tenant);

        $policy = app(ApPaymentImportPolicy::class);
        $this->assertTrue($policy->view($buyer, $import));
        $this->assertTrue($policy->upload($buyer));
        $this->assertTrue($policy->reconcile($buyer));
        $this->assertTrue($policy->discard($buyer, $import));
        $this->assertTrue($policy->update($buyer, $import));
    }

    public function test_import_policy_rejects_cross_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $import = $this->createImport($tenant, $buyer);
        app(CurrentTenant::class)->set($tenant);

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        app(CurrentTenant::class)->set($otherTenant);

        $policy = app(ApPaymentImportPolicy::class);
        $this->assertFalse($policy->view($otherBuyer, $import));
    }

    private function createAllocation(Tenant $tenant): ApPaymentAllocation
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
            'currency' => 'USD',
            'total_amount' => '1000.0000',
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => 'USD',
            'total_amount' => '1000.0000',
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
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'HANDOFF-'.Str::random(6),
            'status' => PurchaseOrderRequestHandoffStatus::Draft,
            'currency' => 'USD',
            'total_amount' => '1000.0000',
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
            'currency' => 'USD',
            'total_amount' => '1000.0000',
            'created_by_user_id' => $buyer->id,
            'source_snapshot' => [],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'lock_version' => 1,
        ]);

        $handoff = ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id, 'number' => 'APH-A-'.Str::random(6), 'status' => 'scheduled',
            'currency' => 'USD', 'total_amount' => '1000.0000', 'lock_version' => 1,
        ]);
        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id, 'purchase_order_id' => $po->id, 'vendor_id' => $vendor->id,
            'number' => 'INV-A-'.Str::random(6),
            'invoice_number' => 'INV-A-'.Str::random(6), 'invoice_number_normalized' => 'inva',
            'status' => SupplierInvoiceStatus::Approved->value,
            'invoice_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal_amount' => '1000.0000', 'total_amount' => '1000.0000',
            'captured_by_user_id' => $buyer->id, 'captured_at' => now(),
            'lock_version' => 1,
        ]);
        $handoff->invoices()->attach($invoice->id, ['tenant_id' => $tenant->id]);

        return ApPaymentAllocation::query()->create([
            'tenant_id' => $tenant->id,
            'ap_payment_handoff_id' => $handoff->id,
            'supplier_invoice_id' => $invoice->id,
            'allocated_amount' => '500.0000',
            'allocation_date' => '2026-06-19',
            'lock_version' => 1,
        ]);
    }

    private function createImport(Tenant $tenant, User $user): ApPaymentImport
    {
        return ApPaymentImport::query()->create([
            'tenant_id' => $tenant->id,
            'batch_id' => (string) Str::uuid(),
            'row_index' => 0,
            'target_status' => 'paid',
            'status' => 'pending',
            'imported_by_user_id' => $user->id,
            'imported_at' => now(),
        ]);
    }

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }
}
