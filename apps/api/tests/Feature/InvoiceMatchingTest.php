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
        $this->assertNotNull($invoice->matching_status);
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
        [$otherTenant] = $this->tenantUserPair();
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $invoice = $this->reviewedInvoice($tenant, $po, $buyer);

        $this->actingAsTenant($tenant, $buyer);
        $this->postJson("/api/supplier-invoices/{$invoice->id}/run-matching", [
            'lockVersion' => 1,
        ])->assertStatus(200);

        $response = $this->getJson("/api/supplier-invoices/{$invoice->id}/match-results");
        $response->assertStatus(200);

        $otherInvoiceId = (string) Str::uuid();

        $this->actingAsTenant($otherTenant, $buyer);
        $response = $this->getJson("/api/supplier-invoices/{$otherInvoiceId}/match-results");
        $response->assertStatus(404);
    }

    private function tenantUserPair(): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => 'buyer']);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): void
    {
        Sanctum::actingAs($user);
        $this->withHeader('X-Tenant-Id', (string) $tenant->id);
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
}
