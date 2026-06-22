<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant ' . Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);
        app(CurrentTenant::class)->set($tenant);
        return [$tenant, $user];
    }

    private function createVendor(Tenant $tenant): Vendor
    {
        return Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Vendor ' . Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createInvoice(
        Tenant $tenant,
        Vendor $vendor,
        string $totalAmount = '1000.0000',
        ?string $paymentStatus = null,
    ): SupplierInvoice {
        $userId = User::factory()->create()->id;

        DB::table('rfqs')->insert([
            'tenant_id' => $tenant->id, 'number' => 'RFQ-' . Str::random(4),
            'title' => 'T', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rfqId = (int) DB::getPdo()->lastInsertId();

        DB::table('quotations')->insert([
            'tenant_id' => $tenant->id, 'rfq_id' => $rfqId, 'vendor_id' => $vendor->id,
            'number' => 'Q-' . Str::random(4), 'status' => 'submitted', 'total_amount' => '1000.00',
            'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $quotationId = (int) DB::getPdo()->lastInsertId();

        DB::table('quotation_versions')->insert([
            'tenant_id' => $tenant->id, 'quotation_id' => $quotationId, 'version_number' => 1,
            'status' => 'submitted', 'is_current' => true, 'currency' => 'USD',
            'total_amount' => '1000.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $quotationVersionId = (int) DB::getPdo()->lastInsertId();

        $recId = Str::uuid()->toString();
        DB::table('rfq_award_recommendations')->insert([
            'id' => $recId, 'tenant_id' => $tenant->id, 'rfq_id' => $rfqId,
            'recommended_vendor_id' => $vendor->id, 'recommended_quotation_id' => $quotationId,
            'recommended_quotation_version_id' => $quotationVersionId, 'status' => 'approved',
            'rationale' => '', 'created_by_user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $handoffId = Str::uuid()->toString();
        DB::table('purchase_order_request_handoffs')->insert([
            'id' => $handoffId, 'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recId, 'rfq_id' => $rfqId,
            'vendor_id' => $vendor->id, 'quotation_id' => $quotationId,
            'quotation_version_id' => $quotationVersionId, 'requested_by_user_id' => $userId,
            'number' => 'HO-' . Str::random(4), 'status' => 'draft', 'currency' => 'USD',
            'total_amount' => '1000.00', 'source_snapshot' => '{}', 'line_snapshot' => '{}',
            'approval_snapshot' => '{}', 'evidence_snapshot' => '{}',
            'readiness_warnings' => '{}', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $poId = Str::uuid()->toString();
        DB::table('purchase_orders')->insert([
            'id' => $poId, 'tenant_id' => $tenant->id,
            'purchase_order_request_handoff_id' => $handoffId,
            'rfq_award_recommendation_id' => $recId, 'rfq_id' => $rfqId,
            'vendor_id' => $vendor->id, 'created_by_user_id' => $userId,
            'number' => 'PO-' . Str::random(4), 'status' => 'issued', 'currency' => 'USD',
            'total_amount' => '1000.0000', 'source_snapshot' => '{}', 'approval_snapshot' => '{}',
            'evidence_snapshot' => '{}', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $number = 'INV-TEST-' . Str::random(6);
        $invoiceId = Str::uuid()->toString();
        $data = [
            'id' => $invoiceId, 'tenant_id' => $tenant->id, 'purchase_order_id' => $poId,
            'vendor_id' => $vendor->id, 'number' => $number, 'invoice_number' => $number,
            'invoice_number_normalized' => strtolower($number),
            'status' => 'approved', 'currency' => 'USD',
            'invoice_date' => now()->toDateString(),
            'subtotal_amount' => $totalAmount, 'tax_amount' => '0.0000',
            'freight_amount' => '0.0000', 'total_amount' => $totalAmount,
            'captured_by_user_id' => $userId, 'captured_at' => now(),
            'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ];

        if ($paymentStatus !== null) {
            $data['payment_status'] = $paymentStatus;
        }

        DB::table('supplier_invoices')->insert($data);

        return SupplierInvoice::query()->findOrFail($invoiceId);
    }

    private function createCreditMemo(
        Tenant $tenant,
        User $actor,
        Vendor $vendor,
        string $totalAmount = '1000.0000',
        ?string $originalInvoiceId = null,
        SupplierCreditMemoStatus $status = SupplierCreditMemoStatus::Open,
    ): SupplierCreditMemo {
        return SupplierCreditMemo::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'CM-TEST-' . Str::random(4),
            'vendor_id' => $vendor->id,
            'original_invoice_id' => $originalInvoiceId,
            'status' => $status,
            'currency' => 'USD',
            'subtotal_amount' => $totalAmount,
            'tax_amount' => '0.0000',
            'freight_amount' => '0.0000',
            'total_amount' => $totalAmount,
            'lock_version' => 1,
        ]);
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function applicationPayload(string $invoiceId, string $amount, int $lockVersion = 1): array
    {
        return [
            'supplierInvoiceId' => $invoiceId,
            'appliedAmount' => $amount,
            'applicationDate' => '2026-06-20',
            'lockVersion' => $lockVersion,
        ];
    }

    public function test_apply_credit_to_open_memo_creates_application(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, '1000.0000');
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '1000.0000', (string) $invoice->id);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '1000.0000'),
            );

        $response->assertCreated();

        $this->assertDatabaseHas('credit_applications', [
            'supplier_credit_memo_id' => $memo->id,
            'supplier_invoice_id' => $invoice->id,
            'applied_amount' => '1000.0000',
        ]);

        $memo->refresh();
        $this->assertSame(SupplierCreditMemoStatus::Closed, $memo->status);

        $invoice->refresh();
        $this->assertSame('reversed', $invoice->payment_status->value);
    }

    public function test_partial_application_moves_memo_to_partially_applied(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, '1000.0000');
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '1000.0000', (string) $invoice->id);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '500.0000'),
            )
            ->assertCreated();

        $memo->refresh();
        $this->assertSame(SupplierCreditMemoStatus::PartiallyApplied, $memo->status);
    }

    public function test_over_application_of_credit_returns_422(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, '2000.0000');
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '1000.0000', (string) $invoice->id);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '1500.0000'),
            )
            ->assertStatus(422);
    }

    public function test_over_application_of_invoice_returns_422(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, '1000.0000');
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '2000.0000', (string) $invoice->id);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '1500.0000'),
            )
            ->assertStatus(422);
    }

    public function test_zero_or_negative_amount_returns_422(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor);
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '1000.0000', (string) $invoice->id);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '0'),
            )
            ->assertStatus(422);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '-1'),
            )
            ->assertStatus(422);
    }

    public function test_vendor_mismatch_returns_422(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendorA = $this->createVendor($tenant);
        $vendorB = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendorA);
        $memo = $this->createCreditMemo($tenant, $buyer, $vendorB, '1000.0000');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '500.0000'),
            )
            ->assertStatus(422);
    }

    public function test_credit_memo_not_in_open_or_partially_applied_returns_403(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor);
        $memo = $this->createCreditMemo(
            $tenant, $buyer, $vendor, '1000.0000', (string) $invoice->id, SupplierCreditMemoStatus::Draft,
        );

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '500.0000'),
            )
             ->assertStatus(403);
    }

    public function test_void_application_reverts_invoice_from_reversed_to_payment_eligible(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, '1000.0000');
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '1000.0000', (string) $invoice->id);

        $appResponse = $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '1000.0000'),
            )
            ->assertCreated();

        $applicationId = $appResponse->json('data.id');
        $applicationLock = $appResponse->json('data.lockVersion');

        $invoice->refresh();
        $this->assertSame('reversed', $invoice->payment_status->value);

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/credit-applications/{$applicationId}", [
                'lockVersion' => $applicationLock,
                'voidReason' => 'Voiding for test purposes.',
            ])
            ->assertOk();

        $invoice->refresh();
        $this->assertSame('payment_eligible', $invoice->payment_status->value);
    }

    public function test_void_application_requires_min_5_char_reason(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, '1000.0000');
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '1000.0000', (string) $invoice->id);

        $appResponse = $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '500.0000'),
            )
            ->assertCreated();

        $applicationId = $appResponse->json('data.id');
        $applicationLock = $appResponse->json('data.lockVersion');

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/credit-applications/{$applicationId}", [
                'lockVersion' => $applicationLock,
                'voidReason' => 'abc',
            ])
            ->assertStatus(422);
    }

    public function test_concurrent_credit_application_returns_409(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, '2000.0000');
        $memo = $this->createCreditMemo($tenant, $buyer, $vendor, '2000.0000', (string) $invoice->id);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '1000.0000', 1),
            )
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '500.0000', 1),
            )
            ->assertStatus(409);
    }

    public function test_cross_tenant_application_show_returns_404(): void
    {
        [$tenantA, $buyerA] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$tenantB, $buyerB] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendorA = $this->createVendor($tenantA);
        $invoice = $this->createInvoice($tenantA, $vendorA);
        $memo = $this->createCreditMemo($tenantA, $buyerA, $vendorA, '1000.0000', (string) $invoice->id);

        $appResponse = $this->actingAsTenant($tenantA, $buyerA)
            ->postJson(
                "/api/supplier-credit-memos/{$memo->id}/applications",
                $this->applicationPayload((string) $invoice->id, '500.0000'),
            )
            ->assertCreated();

        $applicationId = $appResponse->json('data.id');

        $this->actingAsTenant($tenantB, $buyerB)
            ->getJson("/api/credit-applications/{$applicationId}")
            ->assertStatus(404);
    }
}
