<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\Models\GoodsReceiptLine;
use Domains\Receiving\States\GoodsReceiptStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceMatchingTest extends TestCase
{
    use RefreshDatabase;

    private function reviewedInvoice(Tenant $tenant, PurchaseOrder $po, User $buyer, array $overrides = []): SupplierInvoice
    {
        $line = $po->lines->firstOrFail();

        $invoiceNumber = 'INV-MATCH-'.Str::random(6);

        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'vendor_id' => $po->vendor_id,
            'number' => $invoiceNumber,
            'invoice_number' => $invoiceNumber,
            'invoice_number_normalized' => mb_strtolower($invoiceNumber),
            'status' => SupplierInvoiceStatus::Reviewed,
            'invoice_date' => '2026-06-20',
            'due_date' => '2026-07-20',
            'currency' => 'MYR',
            'subtotal_amount' => '120000.0000',
            'tax_amount' => '0.0000',
            'freight_amount' => '0.0000',
            'total_amount' => '120000.0000',
            'captured_by_user_id' => $buyer->id,
            'captured_at' => '2026-06-20 09:00:00',
            'review_started_by_user_id' => $buyer->id,
            'review_started_at' => '2026-06-20 09:30:00',
            'reviewed_by_user_id' => $buyer->id,
            'reviewed_at' => '2026-06-20 10:00:00',
            'lock_version' => 1,
            ...$overrides,
        ]);

        SupplierInvoiceLine::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_invoice_id' => $invoice->id,
            'purchase_order_line_id' => $line->id,
            'line_number' => 1,
            'description_snapshot' => $line->description,
            'quantity_ordered' => $line->quantity,
            'quantity_invoiced' => '10.0000',
            'unit_price' => '12000.0000',
            'line_subtotal' => '120000.0000',
            'notes' => null,
        ]);

        return $invoice->fresh();
    }

    public function test_manual_matching_returns_updated_results(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $response = $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'matchingStatus',
            'lockVersion',
        ]);

        $invoice->refresh();
        $this->assertEquals('matched', $invoice->matching_status);
    }

    public function test_matching_passes_when_all_dimensions_within_tolerance(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 1,
        ])->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('matched', $invoice->matching_status);
    }

    public function test_matching_fails_when_unit_price_exceeds_tolerance(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer);
        $line = $invoice->lines()->firstOrFail();

        $line->forceFill(['unit_price' => '20000.0000', 'line_subtotal' => '20000.0000'])->save();
        $invoice->forceFill(['total_amount' => '20000.0000', 'lock_version' => 2])->save();

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 2,
        ])->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('mismatch', $invoice->matching_status);

        $results = SupplierInvoiceMatchResult::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('dimension', 'unit_price')
            ->where('result', 'fail')
            ->get();
        $this->assertGreaterThan(0, $results->count(), 'Expected at least one unit_price match failure');
    }

    public function test_vendor_identity_mismatch_fails(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $otherVendor = Vendor::query()->create(['tenant_id' => $tenant->id, 'name' => 'Other Vendor', 'status' => 'active']);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer, [
            'vendor_id' => $otherVendor->id,
            'lock_version' => 2,
        ]);

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 2,
        ])->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('mismatch', $invoice->matching_status);
    }

    public function test_matching_on_non_reviewed_invoice_returns_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $invoiceNumber = 'INV-CAPTURED-'.Str::random(6);

        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'vendor_id' => $po->vendor_id,
            'number' => $invoiceNumber,
            'invoice_number' => $invoiceNumber,
            'invoice_number_normalized' => mb_strtolower($invoiceNumber),
            'status' => SupplierInvoiceStatus::Captured,
            'invoice_date' => '2026-06-20',
            'due_date' => '2026-07-20',
            'currency' => 'MYR',
            'subtotal_amount' => '1000.0000',
            'total_amount' => '1000.0000',
            'captured_by_user_id' => $buyer->id,
            'captured_at' => '2026-06-20 09:00:00',
            'lock_version' => 1,
        ]);

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 1,
        ])->assertStatus(409);
    }

    public function test_stale_lock_version_returns_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 999,
        ])->assertStatus(409);
    }

    public function test_run_matching_on_missing_invoice_returns_not_found(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $missingId = (string) Str::uuid();

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$missingId}/run-matching", [
            'lockVersion' => 1,
        ])->assertStatus(404);
    }

    public function test_match_results_list(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 1,
        ])->assertStatus(200);

        $response = $this->getJson("/api/supplier-invoices/{$invoice->id}/match-results");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'lineNumber', 'matchLevel', 'matchType', 'dimension',
                    'expectedValue', 'actualValue', 'result',
                ],
            ],
        ]);

        $results = $response->json('data');
        $this->assertNotEmpty($results);
    }

    public function test_match_results_tenant_scoped(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        [$otherTenant, $otherBuyer] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 1,
        ])->assertStatus(200);

        $response = $this->getJson("/api/supplier-invoices/{$invoice->id}/match-results");
        $response->assertStatus(200);

        // A user from another tenant cannot access this invoice's match results
        $this->actingAsTenant($otherTenant, $otherBuyer);
        $response = $this->getJson("/api/supplier-invoices/{$invoice->id}/match-results");
        $response->assertNotFound();

        // A user from another tenant cannot invoke run-matching on this invoice
        $response = $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);
        $response->assertNotFound();
    }

    private function tenantUserPair(?string $role = null, ?Tenant $existingTenant = null): array
    {
        $role ??= 'buyer';
        $tenant = $existingTenant ?? Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        $this->withHeader('X-Tenant-Id', (string) $tenant->id);

        return $this;
    }

    private function issuedPurchaseOrder(Tenant $tenant, User $buyer): PurchaseOrder
    {
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Vendor',
            'status' => 'active',
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-MATCH-'.Str::random(6),
            'title' => 'Matching test items',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Matching test items.',
            'line_items' => [['name' => 'Widget', 'quantity' => 10, 'unit_of_measure' => 'each', 'currency' => 'MYR']],
        ]);

        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => 'vendor@example.com',
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::random(6),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '120000.00',
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
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '120000.00',
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);

        $quotation->forceFill(['current_version_id' => $version->id])->save();

        $recommendation = RfqAwardRecommendation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'status' => 'approved',
            'recommended_vendor_id' => $vendor->id,
            'recommended_quotation_id' => $quotation->id,
            'recommended_quotation_version_id' => $version->id,
            'rationale' => 'Best fit.',
            'tradeoff_summary' => 'N/A',
            'risk_summary' => 'None',
            'created_by_user_id' => $buyer->id,
            'updated_by_user_id' => $buyer->id,
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
            'approved_by_user_id' => $buyer->id,
            'approved_at' => now(),
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
            'number' => 'POH-MATCH-'.Str::random(6),
            'status' => PurchaseOrderRequestHandoffStatus::Ready,
            'currency' => 'MYR',
            'subtotal_amount' => '120000.00',
            'total_amount' => '120000.00',
            'requested_by_user_id' => $buyer->id,
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name]],
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
            'number' => 'PO-MATCH-'.Str::random(6),
            'status' => PurchaseOrderStatus::Issued,
            'currency' => 'MYR',
            'subtotal_amount' => '120000.00',
            'total_amount' => '120000.00',
            'expected_delivery_date' => '2026-07-15',
            'billing_name' => 'Acme Finance',
            'billing_address' => ['line1' => 'Level 10', 'city' => 'KL', 'country' => 'MY'],
            'shipping_name' => 'Acme Warehouse',
            'shipping_address' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'matching_policy' => 'three_way',
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name]],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'approved_by_user_id' => $buyer->id,
            'approved_at' => now(),
            'issued_by_user_id' => $buyer->id,
            'issued_at' => now(),
            'issue_method' => 'manual_email',
            'lock_version' => 1,
        ]);

        PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'description' => 'Widget',
            'unit' => 'each',
            'quantity' => '10.0000',
            'unit_price' => '12000.0000',
            'subtotal_amount' => '120000.00',
            'total_amount' => '120000.00',
            'currency' => 'MYR',
            'expected_delivery_date' => '2026-07-15',
            'delivery_location' => 'Dock 4',
            'source_snapshot' => [],
            'status' => 'open',
            'cumulative_quantity_accepted' => '10.0000',
            'cumulative_quantity_invoiced' => '0.0000',
        ]);

        return $po->fresh('lines');
    }

    /**
     * @return array<string, array{status: string, note: string|null}>
     */
    private function passingReviewChecklist(): array
    {
        return [
            'completeness' => ['status' => 'pass', 'note' => null],
            'coding' => ['status' => 'pass', 'note' => null],
            'attachment' => ['status' => 'pass', 'note' => null],
            'vendorIdentity' => ['status' => 'pass', 'note' => null],
            'poLinkage' => ['status' => 'pass', 'note' => null],
        ];
    }

    private function capturePayload(PurchaseOrder $po, PurchaseOrderLine $line, array $overrides = []): array
    {
        return array_merge([
            'lockVersion' => 1,
            'invoiceNumber' => 'INV-MISMATCH-'.Str::random(6),
            'invoiceDate' => now()->toDateString(),
            'dueDate' => now()->addDays(30)->toDateString(),
            'lines' => [
                [
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityInvoiced' => '5.0000',
                    'unitPrice' => '12000.0000',
                ],
            ],
        ], $overrides);
    }

    private function createMismatchInvoice(Tenant $tenant, User $buyer): array
    {
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $payload = $this->capturePayload($po, $line, ['lines' => [
            [
                'purchaseOrderLineId' => (string) $line->id,
                'quantityInvoiced' => '10.0000',
                'unitPrice' => '11749.0000',
            ],
        ]]);

        $invoice = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $payload)
            ->assertCreated()
            ->json('data');

        // Complete review
        $started = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk()
            ->json('data');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/complete-review", [
                'lockVersion' => $started['lockVersion'],
                'checklist' => $this->passingReviewChecklist(),
            ])
            ->assertOk();

        // Run matching — produces mismatch
        $matched = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/run-matching", [
                'lockVersion' => $started['lockVersion'] + 1,
            ])
            ->assertOk()
            ->json();

        return ['tenant' => $tenant, 'buyer' => $buyer, 'invoice' => $matched, 'po' => $po, 'line' => $line];
    }

    public function test_exceptions_are_created_after_mismatch_matching(): void
    {
        $result = $this->createMismatchInvoice(...$this->tenantUserPair());

        $this->actingAsTenant($result['tenant'], $result['buyer']);
        $response = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'dimension', 'matchType', 'status',
                    'expectedValue', 'actualValue',
                    'supplierInvoiceLineId', 'purchaseOrderLineId',
                ],
            ],
        ]);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_exception_list_is_tenant_scoped(): void
    {
        [$tenantA, $buyerA] = $this->tenantUserPair();
        $resultA = $this->createMismatchInvoice($tenantA, $buyerA);

        [$tenantB, $buyerB] = $this->tenantUserPair();
        $resultB = $this->createMismatchInvoice($tenantB, $buyerB);

        $this->actingAsTenant($tenantA, $buyerA);
        $responseA = $this->getJson("/api/supplier-invoices/{$resultA['invoice']['id']}/exceptions");
        $responseA->assertOk();

        // Tenant B cannot see Tenant A's exceptions
        $this->actingAsTenant($tenantB, $buyerB);
        $responseB = $this->getJson("/api/supplier-invoices/{$resultA['invoice']['id']}/exceptions");
        $responseB->assertNotFound();
    }

    public function test_buyer_can_resolve_exception_with_explanation(): void
    {
        $result = $this->createMismatchInvoice(...$this->tenantUserPair());

        $this->actingAsTenant($result['tenant'], $result['buyer']);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/resolve",
            [
                'lockVersion' => 1,
                'resolutionType' => 'explanation',
                'explanation' => 'Price variance accepted per buyer discretion — market rate increase since PO issuance.',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'resolved');
    }

    public function test_buyer_can_resolve_exception_with_value_adjustment(): void
    {
        $result = $this->createMismatchInvoice(...$this->tenantUserPair());

        $this->actingAsTenant($result['tenant'], $result['buyer']);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/resolve",
            [
                'lockVersion' => 1,
                'resolutionType' => 'value_adjustment',
                'adjustedValue' => '150.0000',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'resolved');
    }

    public function test_buyer_can_escalate_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        [, $escalatedUser] = $this->tenantUserPair(role: null, existingTenant: $tenant);
        $result = $this->createMismatchInvoice($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/escalate",
            [
                'lockVersion' => 1,
                'escalatedToUserId' => (string) $escalatedUser->id,
                'note' => 'Requires senior review.',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'escalated');
    }

    public function test_cannot_resolve_already_escalated_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        [, $escalatedUser] = $this->tenantUserPair(role: null, existingTenant: $tenant);
        $result = $this->createMismatchInvoice($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/escalate",
            [
                'lockVersion' => 1,
                'escalatedToUserId' => (string) $escalatedUser->id,
                'note' => 'Requires senior review.',
            ]
        )->assertOk();

        // Attempt to resolve escalated exception
        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/resolve",
            [
                'lockVersion' => 2,
                'resolutionType' => 'explanation',
                'explanation' => 'Should be rejected.',
            ]
        );

        $response->assertForbidden();
    }

    public function test_cannot_re_escalate_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        [, $escalatedUser] = $this->tenantUserPair(role: null, existingTenant: $tenant);
        $result = $this->createMismatchInvoice($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/escalate",
            [
                'lockVersion' => 1,
                'escalatedToUserId' => (string) $escalatedUser->id,
                'note' => 'Requires senior review.',
            ]
        )->assertOk();

        // Attempt to escalate again
        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/escalate",
            [
                'lockVersion' => 2,
                'escalatedToUserId' => (string) $escalatedUser->id,
                'note' => 'Escalating again.',
            ]
        );

        $response->assertStatus(409);
    }

    public function test_escalated_user_can_reject_escalated_exception(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        [, $escalatedUser] = $this->tenantUserPair(role: null, existingTenant: $tenant);
        $result = $this->createMismatchInvoice($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/escalate",
            [
                'lockVersion' => 1,
                'escalatedToUserId' => (string) $escalatedUser->id,
                'note' => 'Requires senior review.',
            ]
        )->assertOk();

        // Escalated user can reject via resolve endpoint
        $this->actingAsTenant($tenant, $escalatedUser);
        $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/resolve",
            [
                'lockVersion' => 2,
                'resolutionType' => 'explanation',
                'explanation' => 'Price variance rejected — revert to PO price.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        // Original buyer can no longer modify
        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/resolve", [
                'lockVersion' => 3,
                'resolutionType' => 'explanation',
                'explanation' => 'Should not work.',
            ])
            ->assertStatus(409);
    }

    public function test_all_explanations_advances_invoice_to_ready_for_approval(): void
    {
        $result = $this->createMismatchInvoice(...$this->tenantUserPair());

        $this->actingAsTenant($result['tenant'], $result['buyer']);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');

        foreach ($exceptions as $exception) {
            $this->postJson(
                "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exception['id']}/resolve",
                [
                    'lockVersion' => 1,
                    'resolutionType' => 'explanation',
                    'explanation' => 'Vendor confirmed pricing.',
                ]
            )->assertOk();
        }

        $invoice = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}")->json('data');
        $this->assertEquals('approved', $invoice['status']);
    }

    public function test_value_adjustment_reruns_matching_then_advances(): void
    {
        $result = $this->createMismatchInvoice(...$this->tenantUserPair());

        $this->actingAsTenant($result['tenant'], $result['buyer']);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');

        $adjustedValues = [
            'unit_price' => '12000.0000',
            'line_total' => '120000.0000',
            'invoice_total' => '120000.0000',
        ];

        foreach ($exceptions as $exception) {
            $adjustedValue = $adjustedValues[$exception['dimension']] ?? null;
            if ($adjustedValue !== null) {
                $this->postJson(
                    "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exception['id']}/resolve",
                    [
                        'lockVersion' => $exception['lockVersion'],
                        'resolutionType' => 'value_adjustment',
                        'adjustedValue' => $adjustedValue,
                    ]
                )->assertOk();
            }
        }

        $invoice = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}")->json('data');
        $this->assertEquals('ready_for_approval', $invoice['status']);
    }

    public function test_post_resolution_matching_reruns_when_value_adjustment_exists(): void
    {
        $result = $this->createMismatchInvoice(...$this->tenantUserPair());

        $this->actingAsTenant($result['tenant'], $result['buyer']);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $unitPriceException = collect($exceptions)->firstWhere('dimension', 'unit_price');

        $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$unitPriceException['id']}/resolve",
            [
                'lockVersion' => $unitPriceException['lockVersion'],
                'resolutionType' => 'value_adjustment',
                'adjustedValue' => '12000.0000',
            ]
        )->assertOk();

        // Rerun matching after value adjustment
        $invoice = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}")->json('data');
        $response = $this->postJson("/api/supplier-invoices/{$result['invoice']['id']}/run-matching", [
            'lockVersion' => $invoice['lockVersion'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('matchingStatus', 'matched');
    }

    public function test_exception_resolve_rejects_stale_lock_version(): void
    {
        $result = $this->createMismatchInvoice(...$this->tenantUserPair());

        $this->actingAsTenant($result['tenant'], $result['buyer']);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/resolve",
            [
                'lockVersion' => 999,
                'resolutionType' => 'explanation',
                'explanation' => 'Stale lock version.',
            ]
        );

        $response->assertStatus(409);
    }

    public function test_exception_resolve_requires_buyer_or_admin(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        [, $requester] = $this->tenantUserPair('requester', $tenant);
        $result = $this->createMismatchInvoice($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        // Requester cannot resolve
        $this->actingAsTenant($tenant, $requester);
        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/resolve",
            [
                'lockVersion' => 1,
                'resolutionType' => 'explanation',
                'explanation' => 'Should be forbidden.',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_exception_escalate_requires_valid_tenant_user(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair();
        [$otherTenant, $otherUser] = $this->tenantUserPair();
        $result = $this->createMismatchInvoice($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $exceptions = $this->getJson("/api/supplier-invoices/{$result['invoice']['id']}/exceptions")->json('data');
        $exceptionId = $exceptions[0]['id'];

        // User from another tenant cannot be the escalation target
        $response = $this->postJson(
            "/api/supplier-invoices/{$result['invoice']['id']}/exceptions/{$exceptionId}/escalate",
            [
                'lockVersion' => 1,
                'escalatedToUserId' => (string) $otherUser->id,
                'note' => 'Should be invalid.',
            ]
        );

        $response->assertStatus(422);
    }
}
