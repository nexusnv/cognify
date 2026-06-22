<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierCreditMemoApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role, ?Tenant $existingTenant = null): array
    {
        $tenant = $existingTenant ?? Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);
        app(CurrentTenant::class)->set($tenant);

        return [$tenant, $user];
    }

    private function createVendor(Tenant $tenant): Vendor
    {
        return Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Vendor '.Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createInvoice(Tenant $tenant, Vendor $vendor, string $currency = 'USD'): SupplierInvoice
    {
        $userId = User::factory()->create()->id;

        DB::table('rfqs')->insert([
            'tenant_id' => $tenant->id, 'number' => 'RFQ-'.Str::random(4),
            'title' => 'T', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rfqId = (int) DB::getPdo()->lastInsertId();

        DB::table('quotations')->insert([
            'tenant_id' => $tenant->id, 'rfq_id' => $rfqId, 'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::random(4), 'status' => 'submitted', 'total_amount' => '1000.00',
            'currency' => $currency, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $quotationId = (int) DB::getPdo()->lastInsertId();

        DB::table('quotation_versions')->insert([
            'tenant_id' => $tenant->id, 'quotation_id' => $quotationId, 'version_number' => 1,
            'status' => 'submitted', 'is_current' => true, 'currency' => $currency,
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
            'number' => 'HO-'.Str::random(4), 'status' => 'draft', 'currency' => $currency,
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
            'number' => 'PO-'.Str::random(4), 'status' => 'issued', 'currency' => $currency,
            'total_amount' => '1000.0000', 'source_snapshot' => '{}', 'approval_snapshot' => '{}',
            'evidence_snapshot' => '{}', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $number = 'INV-TEST-'.Str::random(6);
        $invoiceId = Str::uuid()->toString();
        DB::table('supplier_invoices')->insert([
            'id' => $invoiceId, 'tenant_id' => $tenant->id, 'purchase_order_id' => $poId,
            'vendor_id' => $vendor->id, 'number' => $number, 'invoice_number' => $number,
            'invoice_number_normalized' => strtolower($number),
            'status' => 'approved', 'currency' => $currency,
            'invoice_date' => now()->toDateString(),
            'subtotal_amount' => '1000.0000', 'tax_amount' => '0.0000',
            'freight_amount' => '0.0000', 'total_amount' => '1000.0000',
            'captured_by_user_id' => $userId, 'captured_at' => now(),
            'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return SupplierInvoice::query()->findOrFail($invoiceId);
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function validPayload(Vendor $vendor, ?string $originalInvoiceId = null): array
    {
        return [
            'vendorId' => $vendor->id,
            'originalInvoiceId' => $originalInvoiceId,
            'vendorCreditMemoNumber' => 'VCM-'.Str::random(6),
            'creditDate' => now()->format('Y-m-d'),
            'currency' => 'USD',
            'subtotalAmount' => '100.0000',
            'taxAmount' => '10.0000',
            'freightAmount' => '5.0000',
            'totalAmount' => '115.0000',
            'notes' => 'Test credit memo',
            'lines' => [
                [
                    'lineNumber' => 1,
                    'description' => 'Line item 1',
                    'quantity' => '2.0000',
                    'unitPrice' => '50.0000',
                    'taxCode' => 'TX_STD',
                    'taxAmount' => '10.0000',
                    'notes' => 'Line note',
                ],
            ],
        ];
    }

    private function createMemoDirectly(Tenant $tenant, Vendor $vendor, string $status = 'draft', string $number = 'CM-TEST-001'): SupplierCreditMemo
    {
        return SupplierCreditMemo::query()->create([
            'tenant_id' => $tenant->id,
            'number' => $number,
            'vendor_id' => $vendor->id,
            'status' => $status,
            'currency' => 'USD',
            'subtotal_amount' => '100.0000',
            'tax_amount' => '10.0000',
            'freight_amount' => '5.0000',
            'total_amount' => '115.0000',
            'credit_date' => now()->format('Y-m-d'),
            'lock_version' => 1,
        ]);
    }

    private function createApprovalPolicy(Tenant $tenant, User $actor): void
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'CM approval',
            'description' => 'Credit memo approval.',
            'subject_type' => 'supplier_credit_memo',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'supplier_credit_memo',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => 100,
            'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 0]],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Manager review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'user', 'userId' => (string) $actor->id, 'label' => $actor->name],
                        ],
                        'fallbackApprovers' => [],
                    ],
                ],
            ],
            'sla_rules' => [],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }

    // ──── Tests ───────────────────────────────────────────────────────────

    public function test_buyer_can_create_credit_memo_in_draft(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);

        $response = $this->actingAsTenant($tenant, $user)
            ->postJson('/api/supplier-credit-memos', $this->validPayload($vendor));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.vendorId', (string) $vendor->id);

        $number = $response->json('data.number');
        $this->assertMatchesRegularExpression('/^CM-\d{4}-\d{6}$/', $number);

        $this->assertDatabaseHas('supplier_credit_memos', [
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'status' => SupplierCreditMemoStatus::Draft->value,
        ]);

        $this->assertDatabaseCount('supplier_credit_memo_lines', 1);
    }

    public function test_credit_memo_number_is_auto_generated(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);

        $first = $this->actingAsTenant($tenant, $user)
            ->postJson('/api/supplier-credit-memos', $this->validPayload($vendor))
            ->assertStatus(201)
            ->json('data.number');

        $second = $this->actingAsTenant($tenant, $user)
            ->postJson('/api/supplier-credit-memos', $this->validPayload($vendor))
            ->assertStatus(201)
            ->json('data.number');

        $year = now()->format('Y');
        $this->assertMatchesRegularExpression('/^CM-'.$year.'-\d{6}$/', $first);
        $this->assertMatchesRegularExpression('/^CM-'.$year.'-\d{6}$/', $second);

        $firstSeq = (int) explode('-', $first)[2];
        $secondSeq = (int) explode('-', $second)[2];
        $this->assertSame($firstSeq + 1, $secondSeq);
    }

    public function test_credit_memo_vendor_mismatch_returns_409(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendorA = $this->createVendor($tenant);
        $vendorB = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendorA);

        $payload = $this->validPayload($vendorB, $invoice->id);

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/supplier-credit-memos', $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('supplier_credit_memos', 0);
    }

    public function test_credit_memo_math_mismatch_returns_422(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);

        $payload = $this->validPayload($vendor);
        // Line subtotal = 2 * 50 = 100, but header says 200
        $payload['subtotalAmount'] = '200.0000';

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/supplier-credit-memos', $payload)
            ->assertStatus(422);

        $this->assertDatabaseCount('supplier_credit_memos', 0);
    }

    public function test_credit_memo_currency_mismatch_with_original_invoice_returns_409(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor, 'EUR');

        $payload = $this->validPayload($vendor, $invoice->id);
        $payload['currency'] = 'USD';

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/supplier-credit-memos', $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('supplier_credit_memos', 0);
    }

    public function test_credit_memo_requires_at_least_one_line(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);

        $payload = $this->validPayload($vendor);
        $payload['lines'] = [];

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/supplier-credit-memos', $payload)
            ->assertStatus(422);
    }

    public function test_cross_tenant_credit_memo_show_returns_404(): void
    {
        [$tenantA, $userA] = $this->tenantUserPair('buyer');
        [$tenantB, $userB] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenantA);
        $memo = $this->createMemoDirectly($tenantA, $vendor);

        $this->actingAsTenant($tenantB, $userB)
            ->getJson("/api/supplier-credit-memos/{$memo->id}")
            ->assertStatus(404);
    }

    public function test_stale_lock_version_returns_409(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/supplier-credit-memos/{$memo->id}", [
                'lockVersion' => 99,
                'notes' => 'Updated notes',
            ])
            ->assertStatus(409);
    }

    public function test_credit_memo_in_draft_can_be_updated(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/supplier-credit-memos/{$memo->id}", [
                'lockVersion' => 1,
                'notes' => 'Updated via API',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.notes', 'Updated via API');

        $this->assertDatabaseHas('supplier_credit_memos', [
            'id' => $memo->id,
            'notes' => 'Updated via API',
        ]);
    }

    public function test_credit_memo_not_in_draft_cannot_be_updated(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'open');

        // Policy blocks update for non-draft (returns 403 before action runs)
        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/supplier-credit-memos/{$memo->id}", [
                'lockVersion' => 1,
                'notes' => 'Should fail',
            ])
            ->assertStatus(403);
    }

    public function test_credit_memo_can_be_submitted_for_approval(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);
        $this->createApprovalPolicy($tenant, $user);

        $memo = $this->createMemoDirectly($tenant, $vendor, 'draft', 'CM-SUB-001');

        // A line is required for submission
        SupplierCreditMemoLine::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_credit_memo_id' => $memo->id,
            'line_number' => 1,
            'description_snapshot' => 'Line 1',
            'quantity' => '1.0000',
            'unit_price' => '100.0000',
            'line_subtotal' => '100.0000',
        ]);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/submit", [
                'lockVersion' => 1,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_approval');

        $this->assertDatabaseHas('supplier_credit_memos', [
            'id' => $memo->id,
            'status' => SupplierCreditMemoStatus::PendingApproval->value,
        ]);
    }

    public function test_credit_memo_can_be_voided(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'open', 'CM-VOID-001');

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/void", [
                'lockVersion' => 1,
                'voidReason' => 'Credit was issued in error',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.voidReason', 'Credit was issued in error');

        $this->assertDatabaseHas('supplier_credit_memos', [
            'id' => $memo->id,
            'status' => SupplierCreditMemoStatus::Voided->value,
        ]);
    }

    public function test_credit_memo_void_requires_min_5_char_reason(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/void", [
                'lockVersion' => 1,
                'voidReason' => 'abc',
            ])
            ->assertStatus(422);
    }

    public function test_add_line_to_draft_credit_memo_recomputes_header(): void
    {
        [$tenant, $user] = $this->tenantUserPair('buyer');
        $vendor = $this->createVendor($tenant);

        $memo = SupplierCreditMemo::query()->create([
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'number' => 'CM-TEST-001',
            'vendor_credit_memo_number' => 'VCM-001',
            'status' => SupplierCreditMemoStatus::Draft->value,
            'currency' => 'USD',
            'subtotal_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'freight_amount' => '0.0000',
            'total_amount' => '0.0000',
            'credit_date' => now()->format('Y-m-d'),
            'lock_version' => 1,
        ]);

        $response = $this->actingAsTenant($tenant, $user)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/lines", [
                'lockVersion' => 1,
                'lineNumber' => 1,
                'description' => 'New line',
                'quantity' => '2.0000',
                'unitPrice' => '50.0000',
                'taxCode' => 'TX_STD',
                'taxAmount' => '10.0000',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('supplier_credit_memos', [
            'id' => $memo->id,
            'subtotal_amount' => '100.0000',
            'total_amount' => '100.0000',
        ]);

        $this->assertDatabaseHas('supplier_credit_memo_lines', [
            'supplier_credit_memo_id' => $memo->id,
            'line_number' => 1,
            'line_subtotal' => '100.0000',
        ]);
    }

    public function test_requester_role_cannot_view_credit_memos(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair('buyer');
        [, $requester] = $this->tenantUserPair('requester', $tenant);
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/supplier-credit-memos/{$memo->id}")
            ->assertStatus(403);
    }
}
