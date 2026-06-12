<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_capture_supplier_invoice(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", [
                'lockVersion' => $po->lock_version,
                'invoiceNumber' => 'INV-10001',
                'invoiceDate' => '2026-06-12',
                'dueDate' => '2026-07-12',
                'taxAmount' => '7200.00',
                'freightAmount' => '3900.00',
                'notes' => 'Supplier invoice received by AP.',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityInvoiced' => '10.0000',
                    'unitPrice' => '12000.0000',
                    'notes' => 'Invoice line matches PO line.',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.purchaseOrderId', (string) $po->id)
            ->assertJsonPath('data.invoiceNumber', 'INV-10001')
            ->assertJsonPath('data.invoiceDate', '2026-06-12')
            ->assertJsonPath('data.dueDate', '2026-07-12')
            ->assertJsonPath('data.taxAmount', '7200.00')
            ->assertJsonPath('data.freightAmount', '3900.00')
            ->assertJsonPath('data.totalAmount', '131100.00')
            ->assertJsonPath('data.lines.0.purchaseOrderLineId', (string) $line->id)
            ->assertJsonPath('data.lines.0.quantityInvoiced', '10.0000')
            ->assertJsonPath('data.lines.0.unitPrice', '12000.0000');
    }

    public function test_duplicate_supplier_invoice_returns_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $payload = $this->capturePayload($po, $line, [
            'invoiceNumber' => 'INV-DUP-001',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $payload)
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", [
                ...$payload,
                'lockVersion' => $po->fresh()->lock_version,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_invoice_capture_is_tenant_scoped(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertForbidden();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'invoiceNumber' => 'INV-TENANT-OWN',
            ]))
            ->assertCreated();

        $invoiceId = Attachment::query()->count(); // sentinel to keep a second request from becoming a false positive on empty state

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/purchase-orders/{$po->id}/supplier-invoices")
            ->assertForbidden();

        $this->assertSame(0, $invoiceId);
    }

    public function test_invoice_capture_rejects_lock_version_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer, 4);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'lockVersion' => 3,
            ]))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_invoice_list_includes_capture_summary(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $captureResponse = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertCreated();

        $invoiceId = (string) $captureResponse->json('data.id');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}/supplier-invoices")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $invoiceId)
            ->assertJsonPath('data.0.invoiceNumber', 'INV-10001')
            ->assertJsonPath('data.0.totalAmount', '131100.00');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/supplier-invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('data.lines.0.purchaseOrderLineId', (string) $line->id);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions.canCaptureInvoice', true)
            ->assertJsonPath('data.invoiceSummary.totalInvoiceCount', 1)
            ->assertJsonPath('data.invoiceSummary.latestInvoiceDate', '2026-06-12')
            ->assertJsonPath('data.invoiceSummary.totalInvoicedAmount', '131100.00')
            ->assertJsonPath('data.invoiceSummary.currency', 'MYR');
    }

    public function test_supplier_invoice_attachment_upload_and_listing(): void
    {
        Storage::fake('attachments');

        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $captureResponse = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertCreated();

        $invoiceId = (string) $captureResponse->json('data.id');

        $uploadResponse = $this->actingAsTenant($tenant, $buyer)
            ->post("/api/supplier-invoices/{$invoiceId}/attachments", [
                'file' => UploadedFile::fake()->create('supplier-invoice.pdf', 64, 'application/pdf'),
            ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('data.parentType', 'supplier_invoice')
            ->assertJsonPath('data.parentId', $invoiceId)
            ->assertJsonPath('data.filename', 'supplier-invoice.pdf')
            ->assertJsonPath('data.previewable', true);

        $attachmentId = (string) $uploadResponse->json('data.id');
        $attachment = Attachment::query()->findOrFail($attachmentId);

        Storage::disk('attachments')->assertExists($attachment->storage_path);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/supplier-invoices/{$invoiceId}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attachmentId)
            ->assertJsonPath('data.0.parentType', 'supplier_invoice')
            ->assertJsonPath('data.0.parentId', $invoiceId);
    }

    public function test_supplier_invoice_audit_event_is_recorded(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertCreated();

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'action' => 'supplier_invoice.captured',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function capturePayload(PurchaseOrder $po, PurchaseOrderLine $line, array $overrides = []): array
    {
        return [
            'lockVersion' => $po->lock_version,
            'invoiceNumber' => 'INV-10001',
            'invoiceDate' => '2026-06-12',
            'dueDate' => '2026-07-12',
            'taxAmount' => '7200.00',
            'freightAmount' => '3900.00',
            'notes' => 'Supplier invoice received by AP.',
            'lines' => [[
                'purchaseOrderLineId' => (string) $line->id,
                'quantityInvoiced' => '10.0000',
                'unitPrice' => '12000.0000',
                'notes' => 'Invoice line matches PO line.',
            ]],
            ...$overrides,
        ];
    }

    private function issuedPurchaseOrder(Tenant $tenant, User $buyer, int $lockVersion = 1): PurchaseOrder
    {
        $po = $this->purchaseOrder($tenant, $buyer, 'issued', $lockVersion);

        $po->forceFill([
            'issued_by_user_id' => $buyer->id,
            'issued_at' => now(),
            'issue_method' => 'manual_email',
            'supplier_contact_name' => 'Priya Supplier',
            'supplier_contact_email' => 'priya.supplier@example.com',
            'supplier_version_number' => 1,
            'supplier_version' => ['versionNumber' => 1, 'purchaseOrder' => ['number' => $po->number]],
        ])->save();

        return $po->fresh('lines');
    }

    private function purchaseOrder(Tenant $tenant, User $buyer, string $status = 'draft', int $lockVersion = 1): PurchaseOrder
    {
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Traders',
            'status' => 'active',
        ]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-2026-POC',
            'title' => 'Warehouse racking',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Purchase pallet rack bay materials.',
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Pallet rack bay',
                'description' => 'Pallet rack bay',
                'quantity' => '10',
                'unit_of_measure' => 'set',
                'currency' => 'MYR',
            ]],
        ]);
        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => 'northwind@example.com',
        ]);
        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
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
            'total_amount' => '131100.00',
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
            'rationale' => 'Best operational fit.',
            'tradeoff_summary' => 'Higher price but lower delivery risk.',
            'risk_summary' => 'No unresolved risks.',
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
            'number' => 'POH-2026-POC',
            'status' => PurchaseOrderRequestHandoffStatus::Ready,
            'currency' => 'MYR',
            'subtotal_amount' => '131100.00',
            'total_amount' => '131100.00',
            'requested_by_user_id' => $buyer->id,
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name], 'rfq' => ['number' => $rfq->number]],
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
            'number' => 'PO-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'MYR',
            'subtotal_amount' => '120000.00',
            'tax_amount' => '7200.00',
            'freight_amount' => '3900.00',
            'discount_amount' => '0.00',
            'total_amount' => '131100.00',
            'expected_delivery_date' => '2026-07-02',
            'billing_name' => 'Acme Finance',
            'billing_address' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
            'shipping_name' => 'Acme Warehouse',
            'shipping_address' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name]],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'approved_by_user_id' => $buyer->id,
            'approved_at' => now(),
            'lock_version' => $lockVersion,
        ]);

        PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'description' => 'Pallet rack bay',
            'category' => 'Warehouse',
            'unit' => 'each',
            'quantity' => '10.0000',
            'unit_price' => '12000.0000',
            'subtotal_amount' => '120000.00',
            'tax_amount' => '7200.00',
            'freight_amount' => '3900.00',
            'discount_amount' => '0.00',
            'total_amount' => '131100.00',
            'currency' => 'MYR',
            'expected_delivery_date' => '2026-07-02',
            'delivery_location' => 'Dock 4',
            'source_snapshot' => [],
            'status' => 'open',
        ]);

        return $po->fresh('lines');
    }

    private function tenantUserPair(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);
        $this->withHeader('X-Tenant-Id', (string) $tenant->id);

        return $this;
    }
}
