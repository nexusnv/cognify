<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Invoice\Actions\CompleteSupplierInvoiceReview;
use Domains\Invoice\Actions\MarkSupplierInvoiceNeedsInformation;
use Domains\Invoice\Models\SupplierInvoice;
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
use Illuminate\Auth\Access\AuthorizationException;
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
            ->assertJsonPath('data.number', 'INV-2026-000001')
            ->assertJsonPath('data.invoiceNumber', 'INV-10001')
            ->assertJsonPath('data.invoiceDate', '2026-06-12')
            ->assertJsonPath('data.dueDate', '2026-07-12')
            ->assertJsonPath('data.taxAmount', '7200.0000')
            ->assertJsonPath('data.freightAmount', '3900.0000')
            ->assertJsonPath('data.totalAmount', '131100.0000')
            ->assertJsonPath('data.lines.0.descriptionSnapshot', 'Pallet rack bay')
            ->assertJsonPath('data.lines.0.purchaseOrderLineId', (string) $line->id)
            ->assertJsonPath('data.lines.0.quantityInvoiced', '10.0000')
            ->assertJsonPath('data.lines.0.unitPrice', '12000.0000')
            ->assertJsonPath('data.lines.0.lineSubtotal', '120000.0000');
    }

    public function test_supplier_invoice_due_date_can_be_null(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'invoiceNumber' => 'INV-DUE-NULL',
                'dueDate' => null,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.dueDate', null);
    }

    public function test_supplier_invoice_number_increments_within_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $firstPo = $this->issuedPurchaseOrder($tenant, $buyer, 1, 'Northwind Traders', 'SEQ1');
        $secondPo = $this->issuedPurchaseOrder($tenant, $buyer, 1, 'Northwind Traders Two', 'SEQ2');

        $firstLine = $firstPo->lines->firstOrFail();
        $secondLine = $secondPo->lines->firstOrFail();

        $firstResponse = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$firstPo->id}/supplier-invoices", $this->capturePayload($firstPo, $firstLine, [
                'invoiceNumber' => 'INV-SEQ-001',
            ]))
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$secondPo->id}/supplier-invoices", $this->capturePayload($secondPo, $secondLine, [
                'invoiceNumber' => 'INV-SEQ-002',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.number', 'INV-2026-000002');

        $this->assertSame('INV-2026-000001', $firstResponse->json('data.number'));
    }

    public function test_supplier_invoice_capture_rejects_scientific_notation_amounts(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'invoiceNumber' => 'INV-SCI-001',
                'taxAmount' => '1e3',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['taxAmount']);
    }

    public function test_supplier_invoice_capture_rejects_oversized_amounts(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'invoiceNumber' => 'INV-OVERSIZED-001',
                'taxAmount' => '999999999999999.9999',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['taxAmount']);
    }

    public function test_supplier_invoice_capture_rejects_derived_amount_overflow(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'invoiceNumber' => 'INV-OVERFLOW-001',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityInvoiced' => '99999999999999.9999',
                    'unitPrice' => '99999999999999.9999',
                    'notes' => 'Overflow test.',
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.fields.lines.0', 'Line 1: invoice line subtotal exceeds supported precision.');
    }

    public function test_supplier_invoice_capture_rejects_punctuation_only_invoice_numbers(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'invoiceNumber' => '---',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoiceNumber']);
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
            ->assertJsonPath('error.code', 'conflict')
            ->assertJsonPath('error.details.matchingInvoice.number', 'INV-2026-000001')
            ->assertJsonPath('error.details.matchingInvoice.invoiceNumber', 'INV-DUP-001');
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

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/purchase-orders/{$po->id}/supplier-invoices")
            ->assertForbidden();
    }

    public function test_buyer_can_list_supplier_invoice_review_queue(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertCreated();

        $otherPo = $this->issuedPurchaseOrder($tenant, $buyer, 1, 'Contoso Ltd', 'POOTHER');
        $otherLine = $otherPo->lines->firstOrFail();
        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$otherPo->id}/supplier-invoices", $this->capturePayload($otherPo, $otherLine, [
                'invoiceNumber' => 'INV-OTHER-001',
            ]))
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/supplier-invoices?status=captured')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'captured')
            ->assertJsonPath('data.1.status', 'captured');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/supplier-invoices')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_supplier_invoice_review_queue_caps_page_size_and_ignores_unsupported_sort(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line, [
                'invoiceNumber' => 'INV-LATE-001',
                'dueDate' => '2026-07-12',
            ]))
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po->fresh(), $line, [
                'invoiceNumber' => 'INV-EARLY-001',
                'dueDate' => '2026-06-12',
            ]))
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/supplier-invoices?perPage=999999&sort=unexpected')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('data.0.invoiceNumber', 'INV-EARLY-001')
            ->assertJsonPath('data.1.invoiceNumber', 'INV-LATE-001');
    }

    public function test_supplier_invoice_review_queue_rejects_invalid_due_before_filter(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/supplier-invoices?dueBefore=not-a-date')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.fields.dueBefore.0', 'The due before date must be a valid date.');
    }

    public function test_supplier_invoice_review_queue_is_tenant_scoped(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertCreated();

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson('/api/supplier-invoices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_buyer_can_start_and_complete_supplier_invoice_review(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);

        $started = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.reviewStartedByUserId', (string) $buyer->id)
            ->json('data');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice['id'],
            'status' => 'in_review',
            'review_started_by_user_id' => $buyer->id,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/complete-review", [
                'lockVersion' => $started['lockVersion'],
                'notes' => 'Invoice is complete and ready for matching.',
                'checklist' => $this->passingReviewChecklist(),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.reviewedByUserId', (string) $buyer->id)
            ->assertJsonPath('data.reviewChecklist.completeness.status', 'pass')
            ->assertJsonPath('data.reviewChecklistSummary.passed', 5)
            ->assertJsonPath('data.reviewBlockerCount', 0);

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice['id'],
            'status' => 'reviewed',
            'reviewed_by_user_id' => $buyer->id,
        ]);
    }

    public function test_buyer_can_mark_supplier_invoice_needs_information(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);

        $started = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk()
            ->json('data');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/needs-information", [
                'lockVersion' => $started['lockVersion'],
                'notes' => 'Invoice PDF is missing from the record.',
                'checklist' => [
                    ...$this->passingReviewChecklist(),
                    'attachment' => ['status' => 'fail', 'note' => 'Missing supplier invoice PDF.'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_information')
            ->assertJsonPath('data.reviewChecklist.attachment.status', 'fail')
            ->assertJsonPath('data.reviewBlockerCount', 1);

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice['id'],
            'status' => 'needs_information',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'action' => 'supplier_invoice.needs_information',
        ]);
    }

    public function test_supplier_invoice_needs_information_rejects_whitespace_only_notes(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);
        $started = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk()
            ->json('data');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/needs-information", [
                'lockVersion' => $started['lockVersion'],
                'notes' => '   ',
                'checklist' => [
                    ...$this->passingReviewChecklist(),
                    'attachment' => ['status' => 'fail', 'note' => 'Missing supplier invoice PDF.'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.fields.notes.0', 'The notes field is required.');
    }

    public function test_supplier_invoice_review_rejects_invalid_transition_and_stale_lock(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/complete-review", [
                'lockVersion' => $invoice['lockVersion'],
                'checklist' => $this->passingReviewChecklist(),
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');

        $started = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk()
            ->json('data');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/needs-information", [
                'lockVersion' => $started['lockVersion'] - 1,
                'notes' => 'Stale update.',
                'checklist' => [
                    ...$this->passingReviewChecklist(),
                    'attachment' => ['status' => 'fail', 'note' => 'Missing supplier invoice PDF.'],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_supplier_invoice_review_action_enforces_authorization_without_controller(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);
        $started = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk()
            ->json('data');

        [, $requester] = $this->tenantUserPair(TenantRole::Requester->value, $tenant);
        $this->actingAsTenant($tenant, $requester);
        $this->expectException(AuthorizationException::class);

        (new MarkSupplierInvoiceNeedsInformation(app(\App\Audit\AuditRecorder::class)))->handle(
            SupplierInvoice::query()->findOrFail($invoice['id']),
            $requester,
            (int) $started['lockVersion'],
            'Missing invoice.',
            [
                ...$this->passingReviewChecklist(),
                'attachment' => ['status' => 'fail', 'note' => 'Missing supplier invoice PDF.'],
            ],
        );
    }

    public function test_supplier_invoice_complete_review_action_enforces_authorization_without_controller(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);
        $started = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk()
            ->json('data');

        [, $requester] = $this->tenantUserPair(TenantRole::Requester->value, $tenant);
        $this->actingAsTenant($tenant, $requester);
        $this->expectException(AuthorizationException::class);

        (new CompleteSupplierInvoiceReview(app(\App\Audit\AuditRecorder::class)))->handle(
            SupplierInvoice::query()->findOrFail($invoice['id']),
            $requester,
            (int) $started['lockVersion'],
            null,
            $this->passingReviewChecklist(),
        );
    }

    public function test_supplier_invoice_review_requires_buyer_or_admin(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();
        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);
        [, $requester] = $this->tenantUserPair(TenantRole::Requester->value, $tenant);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertForbidden();

        [, $admin] = $this->tenantUserPair(TenantRole::Admin->value, $tenant);

        $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
                'lockVersion' => $invoice['lockVersion'],
            ])
            ->assertOk();
    }

    public function test_supplier_invoice_review_records_audit_events(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();
        $invoice = $this->captureInvoice($tenant, $buyer, $po, $line);

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

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'action' => 'supplier_invoice.review_started',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'action' => 'supplier_invoice.review_completed',
        ]);
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
            ->assertJsonPath('data.0.number', 'INV-2026-000001')
            ->assertJsonPath('data.0.totalAmount', '131100.0000');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/supplier-invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('data.number', 'INV-2026-000001')
            ->assertJsonPath('data.lines.0.purchaseOrderLineId', (string) $line->id);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions.canCaptureInvoice', true)
            ->assertJsonPath('data.invoiceSummary.totalInvoiceCount', 1)
            ->assertJsonPath('data.invoiceSummary.latestInvoiceDate', '2026-06-12')
            ->assertJsonPath('data.invoiceSummary.totalInvoicedAmount', '131100.0000')
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

    /**
     * @return array<string, mixed>
     */
    private function captureInvoice(Tenant $tenant, User $buyer, PurchaseOrder $po, PurchaseOrderLine $line): array
    {
        return $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $this->capturePayload($po, $line))
            ->assertCreated()
            ->json('data');
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

    private function issuedPurchaseOrder(
        Tenant $tenant,
        User $buyer,
        int $lockVersion = 1,
        string $vendorName = 'Northwind Traders',
        string $referenceSuffix = 'POC',
    ): PurchaseOrder
    {
        $po = $this->purchaseOrder($tenant, $buyer, 'issued', $lockVersion, $vendorName, $referenceSuffix);

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

    private function purchaseOrder(
        Tenant $tenant,
        User $buyer,
        string $status = 'draft',
        int $lockVersion = 1,
        string $vendorName = 'Northwind Traders',
        string $referenceSuffix = 'POC',
    ): PurchaseOrder
    {
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $vendorName,
            'status' => 'active',
        ]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-2026-'.$referenceSuffix,
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
            'number' => 'POH-2026-'.$referenceSuffix,
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
            'number' => 'PO-2026-'.$referenceSuffix,
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
        $this->withHeader('X-Tenant-Id', (string) $tenant->id);

        return $this;
    }
}
