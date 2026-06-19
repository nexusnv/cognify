<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Actions\EvaluatePaymentReadiness;
use Domains\AccountsPayable\Actions\HoldSupplierInvoicePayment;
use Domains\AccountsPayable\Actions\MarkApPaymentHandoffReady;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApPaymentHandoffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_handoff_from_eligible_invoices(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $invoiceA = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');
        $invoiceB = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/ap-payment-handoffs', [
                'invoiceIds' => [$invoiceA->id, $invoiceB->id],
                'notes' => 'Test handoff',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonStructure(['data' => ['id', 'number', 'status', 'lockVersion']]);
    }

    public function test_create_handoff_with_on_hold_invoice_returns_error(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $eligibleInvoice = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');
        $onHoldInvoice = $this->createOnHoldInvoice($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/ap-payment-handoffs', [
                'invoiceIds' => [$eligibleInvoice->id, $onHoldInvoice->id],
            ])
            ->assertConflict();
    }

    public function test_create_handoff_with_mixed_currencies_returns_error(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $invoiceMyr = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');
        $invoiceUsd = $this->createPaymentEligibleInvoice($tenant, $buyer, 'USD');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/ap-payment-handoffs', [
                'invoiceIds' => [$invoiceMyr->id, $invoiceUsd->id],
            ])
            ->assertConflict();
    }

    public function test_create_handoff_with_invoice_in_active_handoff_returns_error(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $invoice = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');

        // Create a handoff that includes the same invoice so the second
        // handoff attempt conflicts on active handoff membership.
        $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/ap-payment-handoffs', [
                'invoiceIds' => [$invoice->id],
            ])
            ->assertCreated();

        $invoice->refresh();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/ap-payment-handoffs', [
                'invoiceIds' => [$invoice->id],
            ])
            ->assertConflict();
    }

    public function test_snapshot_contains_invoice_data(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        [$handoff, $invoices] = $this->createDraftHandoff($tenant, $buyer, 1);
        $invoice = $invoices[0];

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/ap-payment-handoffs/{$handoff->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['snapshot' => ['invoices']]])
            ->assertJsonPath('data.snapshot.invoices.0.id', (string) $invoice->id)
            ->assertJsonPath('data.snapshot.invoices.0.invoiceNumber', $invoice->invoice_number);
    }

    public function test_mark_handoff_ready(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');

        [$handoff] = $this->createDraftHandoff($tenant, $buyer, 1);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/ap-payment-handoffs/{$handoff->id}/ready", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.lockVersion', 2);
    }

    public function test_export_handoff_json(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');

        [$handoff] = $this->createDraftHandoff($tenant, $buyer, 1);

        $handoff = $this->markHandoffReady($handoff, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/ap-payment-handoffs/{$handoff->id}/export.json")
            ->assertOk()
            ->assertJsonPath('format', 'json')
            ->assertJsonStructure(['exportedAt', 'handoff' => ['number', 'status', 'invoices']]);

        $this->assertDatabaseHas('ap_payment_handoffs', [
            'id' => $handoff->id,
            'status' => ApPaymentHandoffStatus::Ready->value,
        ]);
    }

    public function test_export_handoff_csv(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');

        [$handoff] = $this->createDraftHandoff($tenant, $buyer, 1);

        $handoff = $this->markHandoffReady($handoff, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->get("/api/ap-payment-handoffs/{$handoff->id}/export.csv")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_cancel_handoff_returns_invoices_to_eligible(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $invoice = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');

        [$handoff] = $this->createDraftHandoff($tenant, $buyer, 1);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/ap-payment-handoffs/{$handoff->id}/cancel", [
                'reason' => 'Changed our mind',
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => SupplierInvoicePaymentStatus::PaymentEligible->value,
        ]);
    }

    public function test_refresh_handoff_snapshot_rebuilds_from_live_data(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        [$handoff, $invoices] = $this->createDraftHandoff($tenant, $buyer, 1);
        $invoice = $invoices[0];

        // Give the vendor a tax identifier so the only outstanding readiness
        // warning is the invoice's missing due date.
        $invoice->vendor->forceFill(['metadata' => ['tax_id' => 'TX-12345']])->save();

        // The draft snapshot was built from an invoice missing its due date, so
        // it carries an advisory readiness warning.
        $this->assertNotEmpty($handoff->fresh()->readiness_warnings);

        // Fill the gap on the underlying invoice, then refresh the snapshot.
        $invoice->forceFill(['due_date' => now()->addDays(14)->toDateString()])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/ap-payment-handoffs/{$handoff->id}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.lockVersion', 2);

        // The refreshed snapshot still carries the invoice, and re-reading the
        // live data cleared the stale "missing due date" warning.
        $refreshed = $handoff->fresh();
        $this->assertNotEmpty($refreshed->snapshot['invoices'] ?? []);
        $this->assertEmpty($refreshed->readiness_warnings);
    }

    public function test_refresh_snapshot_rejected_for_non_draft_handoff(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        [$handoff] = $this->createDraftHandoff($tenant, $buyer, 1);
        $handoff = $this->markHandoffReady($handoff, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/ap-payment-handoffs/{$handoff->id}/refresh")
            ->assertConflict();
    }

    public function test_mark_handoff_ready_succeeds_with_readiness_warnings(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        [$handoff] = $this->createDraftHandoff($tenant, $buyer, 1);

        // Drafts built from the test helper carry warnings (invoices lack a due
        // date). Readiness warnings are advisory and must not block marking ready.
        $this->assertNotEmpty($handoff->fresh()->readiness_warnings);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/ap-payment-handoffs/{$handoff->id}/ready", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.lockVersion', 2);
    }

    public function test_cross_tenant_handoff_access_denied(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');
        [$handoff] = $this->createDraftHandoff($tenant, $buyer, 1);

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/ap-payment-handoffs/{$handoff->id}")
            ->assertForbidden();
    }

    public function test_non_ap_user_cannot_create_handoff(): void
    {
        [$tenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);
        $invoice = $this->createPaymentEligibleInvoice($tenant, $requester, 'MYR');

        $this->actingAsTenant($tenant, $requester)
            ->postJson('/api/ap-payment-handoffs', [
                'invoiceIds' => [$invoice->id],
            ])
            ->assertForbidden();
    }

    private function createPaymentEligibleInvoice(Tenant $tenant, User $buyer, string $currency = 'MYR'): SupplierInvoice
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
            'currency' => $currency,
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
            'currency' => $currency,
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
            'currency' => $currency,
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
            'currency' => $currency,
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
            'currency' => $currency,
            'subtotal_amount' => '1000.0000',
            'total_amount' => '1000.0000',
            'captured_by_user_id' => $buyer->id,
            'captured_at' => now(),
            'lock_version' => 1,
        ]);

        app(EvaluatePaymentReadiness::class)->handle($invoice, $buyer);

        return $invoice->fresh();
    }

    private function createOnHoldInvoice(Tenant $tenant, User $buyer): SupplierInvoice
    {
        $invoice = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');

        app(HoldSupplierInvoicePayment::class)->handle(
            $invoice, $buyer, $invoice->lock_version, 'Test hold reason.'
        );

        return $invoice->fresh();
    }

    /**
     * @return array{ApPaymentHandoff, array<int, SupplierInvoice>}
     */
    private function createDraftHandoff(Tenant $tenant, User $buyer, int $invoiceCount = 1): array
    {
        $invoices = [];
        for ($i = 0; $i < $invoiceCount; $i++) {
            $invoices[] = $this->createPaymentEligibleInvoice($tenant, $buyer, 'MYR');
        }

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/ap-payment-handoffs', [
                'invoiceIds' => array_map(fn (SupplierInvoice $inv) => $inv->id, $invoices),
            ]);

        $handoffId = $response->assertCreated()->json('data.id');

        $handoff = ApPaymentHandoff::query()->findOrFail($handoffId);

        return [$handoff, $invoices];
    }

    private function markHandoffReady(ApPaymentHandoff $handoff, User $buyer): ApPaymentHandoff
    {
        return app(MarkApPaymentHandoffReady::class)->handle(
            $handoff, $buyer, (int) $handoff->lock_version
        );
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function tenantUser(Tenant $tenant, string $role): User
    {
        [, $user] = $this->tenantUserPair($role, $tenant);

        return $user;
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUserPair(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }
}
