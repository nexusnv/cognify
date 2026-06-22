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
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierCreditMemoExceptionApiTest extends TestCase
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

    private function createVendor(Tenant $tenant): Vendor
    {
        return Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Vendor '.Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createInvoice(Tenant $tenant, Vendor $vendor): SupplierInvoice
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
            'number' => 'HO-'.Str::random(4), 'status' => 'draft', 'currency' => 'USD',
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
            'number' => 'PO-'.Str::random(4), 'status' => 'issued', 'currency' => 'USD',
            'total_amount' => '1000.0000', 'source_snapshot' => '{}', 'approval_snapshot' => '{}',
            'evidence_snapshot' => '{}', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $number = 'INV-TEST-'.Str::random(6);
        $invoiceId = Str::uuid()->toString();
        DB::table('supplier_invoices')->insert([
            'id' => $invoiceId, 'tenant_id' => $tenant->id, 'purchase_order_id' => $poId,
            'vendor_id' => $vendor->id, 'number' => $number, 'invoice_number' => $number,
            'invoice_number_normalized' => strtolower($number),
            'status' => 'approved', 'currency' => 'USD',
            'invoice_date' => now()->toDateString(),
            'subtotal_amount' => '1000.0000', 'tax_amount' => '0.0000',
            'freight_amount' => '0.0000', 'total_amount' => '1000.0000',
            'captured_by_user_id' => $userId, 'captured_at' => now(),
            'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return SupplierInvoice::query()->findOrFail($invoiceId);
    }

    private function createMemoDirectly(
        Tenant $tenant,
        Vendor $vendor,
        string $number = 'CM-TEST-001',
        ?string $originalInvoiceId = null,
        ?string $vendorCreditMemoNumber = null,
    ): SupplierCreditMemo {
        return SupplierCreditMemo::query()->create([
            'tenant_id' => $tenant->id,
            'number' => $number,
            'vendor_id' => $vendor->id,
            'original_invoice_id' => $originalInvoiceId,
            'vendor_credit_memo_number' => $vendorCreditMemoNumber,
            'status' => SupplierCreditMemoStatus::Draft,
            'currency' => 'USD',
            'subtotal_amount' => '1000.0000',
            'tax_amount' => '0.0000',
            'freight_amount' => '0.0000',
            'total_amount' => '1000.0000',
            'credit_date' => now()->format('Y-m-d'),
            'lock_version' => 1,
        ]);
    }

    private function createException(
        Tenant $tenant,
        SupplierCreditMemo $memo,
        string $exceptionType = 'duplicate_credit',
        string $severity = 'warning',
    ): SupplierCreditMemoException {
        return SupplierCreditMemoException::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_credit_memo_id' => $memo->id,
            'exception_type' => $exceptionType,
            'severity' => $severity,
            'description' => 'Test exception for '.$exceptionType,
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

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    public function test_duplicate_credit_creates_warning_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $invoice = $this->createInvoice($tenant, $vendor);

        $this->createMemoDirectly($tenant, $vendor, 'CM-EXIST-001', (string) $invoice->id, 'VCM-DUP-001');

        $payload = [
            'vendorId' => (int) $vendor->id,
            'originalInvoiceId' => (string) $invoice->id,
            'vendorCreditMemoNumber' => 'VCM-DUP-001',
            'creditDate' => '2026-06-20',
            'currency' => 'USD',
            'subtotalAmount' => '500.0000',
            'taxAmount' => '0.0000',
            'freightAmount' => '0.0000',
            'totalAmount' => '500.0000',
            'lines' => [
                [
                    'lineNumber' => 1,
                    'description' => 'Duplicate test line',
                    'quantity' => '1.0000',
                    'unitPrice' => '500.0000',
                    'taxAmount' => '0.0000',
                ],
            ],
        ];

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/supplier-credit-memos', $payload)
            ->assertCreated();

        $newMemoId = $response->json('data.id');

        $this->assertDatabaseHas('supplier_credit_memo_exceptions', [
            'supplier_credit_memo_id' => $newMemoId,
            'exception_type' => 'duplicate_credit',
            'severity' => 'warning',
        ]);

        $exceptionCount = SupplierCreditMemoException::query()
            ->where('supplier_credit_memo_id', $newMemoId)
            ->count();
        $this->assertSame(1, $exceptionCount);
    }

    public function test_tax_code_mismatch_creates_warning_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $originalInvoice = $this->createInvoice($tenant, $vendor);

        $poLineId = Str::uuid()->toString();
        $poId = (string) $originalInvoice->purchase_order_id;
        DB::table('purchase_order_lines')->insert([
            'id' => $poLineId,
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $poId,
            'line_number' => 1,
            'description' => 'Widget A',
            'unit' => 'each',
            'quantity' => '10.0000',
            'unit_price' => '100.0000',
            'subtotal_amount' => '1000.00',
            'tax_amount' => '0.00',
            'total_amount' => '1000.00',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceLineId = Str::uuid()->toString();
        DB::table('supplier_invoice_lines')->insert([
            'id' => $invoiceLineId,
            'tenant_id' => $tenant->id,
            'supplier_invoice_id' => $originalInvoice->id,
            'purchase_order_line_id' => $poLineId,
            'line_number' => 1,
            'description_snapshot' => 'Widget A',
            'quantity_ordered' => '10.0000',
            'quantity_invoiced' => '10.0000',
            'unit_price' => '100.0000',
            'line_subtotal' => '1000.0000',
            'tax_code' => 'TX_STD',
            'tax_amount' => '80.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/supplier-credit-memos', [
                'vendorId' => (int) $vendor->id,
                'originalInvoiceId' => (string) $originalInvoice->id,
                'vendorCreditMemoNumber' => 'VCM-TAX-001',
                'creditDate' => '2026-06-20',
                'currency' => 'USD',
                'subtotalAmount' => '1000.0000',
                'taxAmount' => '80.0000',
                'freightAmount' => '0.0000',
                'totalAmount' => '1080.0000',
                'lines' => [
                    [
                        'lineNumber' => 1,
                        'description' => 'Widget A return',
                        'quantity' => '10.0000',
                        'unitPrice' => '100.0000',
                        'taxCode' => 'TX_ZERO',
                        'taxAmount' => '0.0000',
                        'originalInvoiceLineId' => $invoiceLineId,
                    ],
                ],
            ]);

        $response->assertCreated();
        $newMemoId = (string) $response->json('data.id');

        $exception = SupplierCreditMemoException::query()
            ->where('supplier_credit_memo_id', $newMemoId)
            ->where('exception_type', 'tax_code_mismatch')
            ->first();
        $this->assertNotNull($exception);
        $this->assertSame('warning', $exception->severity);
    }

    public function test_blocking_exception_prevents_submit(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'CM-BLOCK-001');

        SupplierCreditMemoLine::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_credit_memo_id' => $memo->id,
            'line_number' => 1,
            'description_snapshot' => 'Test line',
            'quantity' => '1.0000',
            'unit_price' => '1000.0000',
            'line_subtotal' => '1000.0000',
        ]);

        $this->createException($tenant, $memo, 'math_error', 'blocking');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/submit", [
                'lockVersion' => 1,
            ])
            ->assertStatus(409);
    }

    public function test_resolved_exception_does_not_prevent_submit(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $this->createApprovalPolicy($tenant, $buyer);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'CM-RESOLV-001');

        SupplierCreditMemoLine::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_credit_memo_id' => $memo->id,
            'line_number' => 1,
            'description_snapshot' => 'Test line',
            'quantity' => '1.0000',
            'unit_price' => '1000.0000',
            'line_subtotal' => '1000.0000',
        ]);

        $exception = $this->createException($tenant, $memo, 'math_error', 'blocking');

        $exception->forceFill([
            'resolved_at' => now(),
            'resolved_by_user_id' => $buyer->id,
            'resolution_type' => 'accepted',
            'resolution_notes' => 'Verified correct.',
            'lock_version' => 2,
        ])->save();

        $memo->refresh();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/submit", [
                'lockVersion' => $memo->lock_version,
            ])
            ->assertOk();
    }

    public function test_acknowledge_exception_records_acknowledged_at(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'CM-ACK-001');
        $exception = $this->createException($tenant, $memo);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/acknowledge", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.acknowledgedAt', fn ($val) => $val !== null);

        $this->assertNotNull($exception->fresh()->acknowledged_at);
    }

    public function test_resolve_exception_with_resolution_type_and_notes(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'CM-RES-001');
        $exception = $this->createException($tenant, $memo);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/resolve", [
                'lockVersion' => 1,
                'resolutionType' => SupplierCreditMemoExceptionResolutionType::Accepted->value,
                'resolutionNotes' => 'Reviewed and accepted by finance.',
            ])
            ->assertOk()
            ->assertJsonPath('data.resolutionType', 'accepted')
            ->assertJsonPath('data.resolutionNotes', 'Reviewed and accepted by finance.')
            ->assertJsonPath('data.resolvedAt', fn ($val) => $val !== null);

        $this->assertNotNull($exception->fresh()->resolved_at);
    }

    public function test_resolve_without_resolution_notes_returns_422(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'CM-RES2-001');
        $exception = $this->createException($tenant, $memo);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/resolve", [
                'lockVersion' => 1,
                'resolutionType' => SupplierCreditMemoExceptionResolutionType::Accepted->value,
                'resolutionNotes' => '',
            ])
            ->assertStatus(422);
    }

    public function test_escalate_exception_records_escalated_at(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'CM-ESC-001');
        $exception = $this->createException($tenant, $memo);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/escalate", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.escalatedAt', fn ($val) => $val !== null);

        $this->assertNotNull($exception->fresh()->escalated_at);
    }

    public function test_acknowledge_already_acknowledged_returns_403(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = $this->createVendor($tenant);
        $memo = $this->createMemoDirectly($tenant, $vendor, 'CM-ACK2-001');
        $exception = $this->createException($tenant, $memo);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/acknowledge", [
                'lockVersion' => 1,
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/acknowledge", [
                'lockVersion' => 2,
            ])
            ->assertStatus(403);
    }

    public function test_cross_tenant_exception_list_returns_404(): void
    {
        [$tenantA, $buyerA] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$tenantB, $buyerB] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendorA = $this->createVendor($tenantA);
        $memo = $this->createMemoDirectly($tenantA, $vendorA, 'CM-XT-001');

        $this->actingAsTenant($tenantB, $buyerB)
            ->getJson("/api/supplier-credit-memos/{$memo->id}/exceptions")
            ->assertStatus(404);
    }

    public function test_cross_tenant_acknowledge_returns_404(): void
    {
        [$tenantA, $buyerA] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$tenantB, $buyerB] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendorA = $this->createVendor($tenantA);
        $memo = $this->createMemoDirectly($tenantA, $vendorA, 'CM-XT-002');
        $exception = $this->createException($tenantA, $memo);

        $this->actingAsTenant($tenantB, $buyerB)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/acknowledge", [
                'lockVersion' => 1,
            ])
            ->assertStatus(404);
    }

    public function test_cross_tenant_resolve_returns_404(): void
    {
        [$tenantA, $buyerA] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$tenantB, $buyerB] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendorA = $this->createVendor($tenantA);
        $memo = $this->createMemoDirectly($tenantA, $vendorA, 'CM-XT-003');
        $exception = $this->createException($tenantA, $memo);

        $this->actingAsTenant($tenantB, $buyerB)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/resolve", [
                'lockVersion' => 1,
                'resolutionType' => 'accepted',
                'resolutionNotes' => 'Cross-tenant attempt to resolve.',
            ])
            ->assertStatus(404);
    }

    public function test_cross_tenant_escalate_returns_404(): void
    {
        [$tenantA, $buyerA] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$tenantB, $buyerB] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendorA = $this->createVendor($tenantA);
        $memo = $this->createMemoDirectly($tenantA, $vendorA, 'CM-XT-004');
        $exception = $this->createException($tenantA, $memo);

        $this->actingAsTenant($tenantB, $buyerB)
            ->postJson("/api/supplier-credit-memos/{$memo->id}/exceptions/{$exception->id}/escalate", [
                'lockVersion' => 1,
            ])
            ->assertStatus(404);
    }
}
