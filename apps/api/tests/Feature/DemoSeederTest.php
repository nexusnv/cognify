<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Notifications\NotificationRecord;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalTask;
use Domains\Attachment\Models\Attachment;
use Domains\Award\Models\Award;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Fulfillment\Models\FulfillmentTrackingEvent;
use Domains\Fulfillment\Models\Shipment;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Project\Models\ProcurementProject;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderStatus;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderType;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqScorecardStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_models_are_tenant_scoped_and_cast_metadata(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Corp',
        ]);

        $admin = User::factory()->create();
        $tenant->users()->attach($admin->id, ['role' => TenantRole::Admin->value]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Global Freight',
            'status' => 'active',
            'category' => 'logistics',
            'risk_rating' => 'low',
            'metadata' => [
                'region' => 'APAC',
            ],
        ]);

        $project = ProcurementProject::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $admin->id,
            'number' => 'PRJ-1001',
            'name' => 'Office relocation',
            'status' => 'draft',
            'budget_amount' => '125000.00',
            'currency' => 'USD',
            'metadata' => [
                'program' => 'workspace-refresh',
            ],
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'requisition_id' => null,
            'number' => 'RFQ-2001',
            'title' => 'Office relocation shortlist',
            'status' => 'open',
            'due_at' => now()->addDays(7),
            'metadata' => [
                'invited_vendors' => 3,
            ],
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'QUO-3001',
            'status' => 'submitted',
            'total_amount' => '98500.00',
            'currency' => 'USD',
            'metadata' => [
                'lead_time_days' => 21,
            ],
        ]);

        $approvalTask = ApprovalTask::query()->create([
            'tenant_id' => $tenant->id,
            'assignee_id' => $admin->id,
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'title' => 'Approve relocation quotation',
            'status' => 'active',
            'due_at' => now()->addDays(2),
            'metadata' => [
                'stage' => 'finance',
            ],
        ]);

        $award = Award::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            'number' => 'AWD-4001',
            'status' => 'awarded',
            'total_amount' => '98500.00',
            'currency' => 'USD',
            'decided_at' => now(),
            'metadata' => [
                'rationale' => 'Best delivery confidence',
            ],
        ]);

        $demoSeedRun = DemoSeedRun::query()->create([
            'name' => 'local-demo',
            'seeded_at' => now(),
            'metadata' => [
                'records' => 5,
            ],
        ]);

        $vendor = $vendor->refresh();
        $project = $project->refresh();
        $rfq = $rfq->refresh();
        $quotation = $quotation->refresh();
        $approvalTask = $approvalTask->refresh();
        $award = $award->refresh();
        $demoSeedRun = $demoSeedRun->refresh();

        $this->assertSame($tenant->id, $vendor->tenant_id);
        $this->assertSame($tenant->id, $project->tenant_id);
        $this->assertSame($tenant->id, $rfq->tenant_id);
        $this->assertSame($tenant->id, $quotation->tenant_id);
        $this->assertSame($tenant->id, $approvalTask->tenant_id);
        $this->assertSame($tenant->id, $award->tenant_id);

        $this->assertSame('APAC', $vendor->metadata['region']);
        $this->assertSame('125000.00', $project->budget_amount);
        $this->assertSame(['program' => 'workspace-refresh'], $project->metadata);
        $this->assertSame(3, $rfq->metadata['invited_vendors']);
        $this->assertInstanceOf(Carbon::class, $rfq->due_at);
        $this->assertSame('98500.00', $quotation->total_amount);
        $this->assertSame(21, $quotation->metadata['lead_time_days']);
        $this->assertSame('finance', $approvalTask->metadata['stage']);
        $this->assertSame('98500.00', $award->total_amount);
        $this->assertInstanceOf(Carbon::class, $award->decided_at);
        $this->assertSame('Best delivery confidence', $award->metadata['rationale']);
        $this->assertSame('local-demo', $demoSeedRun->name);
        $this->assertInstanceOf(Carbon::class, $demoSeedRun->seeded_at);
        $this->assertSame(['records' => 5], $demoSeedRun->metadata);
    }

    public function test_preview_models_reject_cross_tenant_links(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Corp',
        ]);
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Corp',
        ]);

        $admin = User::factory()->create();
        $tenant->users()->attach($admin->id, ['role' => TenantRole::Admin->value]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Global Freight',
            'status' => 'active',
        ]);
        $otherVendor = Vendor::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Freight',
            'status' => 'active',
        ]);
        $project = ProcurementProject::query()->create([
            'tenant_id' => $otherTenant->id,
            'owner_id' => null,
            'number' => 'PRJ-9999',
            'name' => 'Other tenant project',
            'status' => 'draft',
            'budget_amount' => '1000.00',
            'currency' => 'USD',
        ]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => null,
            'requisition_id' => null,
            'number' => 'RFQ-9999',
            'title' => 'Cross tenant check',
            'status' => 'open',
        ]);

        $this->expectException(InvalidArgumentException::class);

        Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $otherVendor->id,
            'number' => 'QUO-9999',
            'status' => 'submitted',
            'total_amount' => '100.00',
            'currency' => 'USD',
        ]);
    }

    public function test_demo_seeds_invoice_exceptions(): void
    {
        $this->seed();

        $invoice = SupplierInvoice::query()
            ->where('matching_status', SupplierInvoiceStatus::Mismatch->value)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->exception_summary);
        $this->assertGreaterThan(0, $invoice->exception_summary['total']);

        $exceptions = $invoice->exceptions;
        $this->assertNotEmpty($exceptions);
        $this->assertEquals('open', $exceptions->first()->status);
    }

    public function test_awards_reject_cross_tenant_project_links(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Corp',
        ]);
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Corp',
        ]);

        $admin = User::factory()->create();
        $tenant->users()->attach($admin->id, ['role' => TenantRole::Admin->value]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Global Freight',
            'status' => 'active',
        ]);
        $project = ProcurementProject::query()->create([
            'tenant_id' => $otherTenant->id,
            'owner_id' => null,
            'number' => 'PRJ-9999',
            'name' => 'Other tenant project',
            'status' => 'draft',
            'budget_amount' => '1000.00',
            'currency' => 'USD',
        ]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => null,
            'requisition_id' => null,
            'number' => 'RFQ-9999',
            'title' => 'Cross tenant check',
            'status' => 'open',
        ]);
        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'QUO-9999',
            'status' => 'submitted',
            'total_amount' => '100.00',
            'currency' => 'USD',
        ]);

        $this->expectException(InvalidArgumentException::class);

        Award::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            'number' => 'AWD-9999',
            'status' => 'awarded',
            'total_amount' => '100.00',
            'currency' => 'USD',
        ]);
    }

    public function test_local_demo_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(3, Tenant::query()->count());
        $this->assertSame(7, User::query()->count());
        $this->assertSame(10, Requisition::query()->count());
        $this->assertSame(9, Vendor::query()->count());
        $this->assertSame(3, ProcurementProject::query()->count());
        $this->assertSame(5, Rfq::query()->count());
        $this->assertSame(5, Quotation::query()->count());
        $this->assertSame(15, ApprovalTask::query()->count());
        $this->assertSame(2, Award::query()->count());
        $this->assertSame(12, Attachment::query()->count());
        $this->assertSame(5, NotificationRecord::query()->count());
        $this->assertSame(6, RfqInvitation::query()->count());
        $this->assertSame(4, QuotationVersion::query()->count());
        $this->assertSame(4, QuotationNormalization::query()->count());
        $this->assertSame(1, QuotationScoringTemplate::query()->count());
        $this->assertSame(1, RfqScorecard::query()->count());
        $this->assertSame(12, RfqAwardRecommendation::query()->count());
        $this->assertSame(1, QuotationComparisonNote::query()->count());
        $this->assertSame(1, ApprovalDelegation::query()->count());
        $this->assertSame(1, DemoSeedRun::query()->count());

        $acme = Tenant::query()->where('name', 'Acme Procurement')->firstOrFail();
        $northwind = Tenant::query()->where('name', 'Northwind Sourcing')->firstOrFail();
        $beta = Tenant::query()->where('name', 'Beta Corp Sandbox')->firstOrFail();

        $this->assertDatabaseHas('tenants', ['slug' => 'acme', 'name' => 'Acme Procurement']);
        $this->assertDatabaseHas('tenants', ['slug' => 'northwind', 'name' => 'Northwind Sourcing']);
        $this->assertDatabaseHas('tenants', ['slug' => 'beta', 'name' => 'Beta Corp Sandbox']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'name' => 'Test User']);
        $this->assertDatabaseHas('users', ['email' => 'buyer@example.com', 'name' => 'Buyer User']);
        $this->assertDatabaseHas('users', ['email' => 'approver@example.com', 'name' => 'Approver User']);
        $this->assertDatabaseHas('users', ['email' => 'finance@example.com', 'name' => 'Finance User']);
        $this->assertDatabaseHas('users', ['email' => 'auditor@example.com', 'name' => 'Audit User']);
        $this->assertDatabaseHas('users', ['email' => 'vendor.manager@example.com', 'name' => 'Vendor Manager']);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'name' => 'Admin User']);
        $buyer = User::where('email', 'buyer@example.com')->firstOrFail();
        $finance = User::where('email', 'finance@example.com')->firstOrFail();

        $this->assertSame(TenantRole::Requester->value, $acme->roleFor(User::where('email', 'test@example.com')->firstOrFail()));
        $this->assertSame(TenantRole::Buyer->value, $acme->roleFor(User::where('email', 'buyer@example.com')->firstOrFail()));
        $this->assertSame(TenantRole::Approver->value, $acme->roleFor(User::where('email', 'approver@example.com')->firstOrFail()));
        $this->assertSame(TenantRole::Approver->value, $acme->roleFor(User::where('email', 'finance@example.com')->firstOrFail()));
        $this->assertSame(TenantRole::Admin->value, $acme->roleFor(User::where('email', 'auditor@example.com')->firstOrFail()));
        $this->assertSame(TenantRole::Buyer->value, $northwind->roleFor(User::where('email', 'vendor.manager@example.com')->firstOrFail()));
        $this->assertSame(TenantRole::Admin->value, $acme->roleFor(User::where('email', 'admin@example.com')->firstOrFail()));
        $this->assertSame(TenantRole::Admin->value, $beta->roleFor(User::where('email', 'admin@example.com')->firstOrFail()));

        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-0001', 'title' => 'HQ workplace refresh']);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-0002', 'title' => 'Engineering laptop refresh']);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-0003', 'title' => 'Security audit services']);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-1001', 'title' => 'Regional warehouse supplies']);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-1002', 'title' => 'Fleet maintenance review']);
        $this->assertDatabaseHas('requisitions', [
            'number' => 'REQ-2026-0003',
            'status' => RequisitionStatus::PendingApproval->value,
            'submitted_at' => '2026-05-15 09:00:00',
        ]);
        $this->assertDatabaseHas('requisitions', [
            'number' => 'REQ-2026-1001',
            'status' => RequisitionStatus::Approved->value,
            'submitted_at' => '2026-05-15 09:00:00',
        ]);
        $this->assertDatabaseHas('requisitions', [
            'number' => 'REQ-2026-1002',
            'status' => RequisitionStatus::Rejected->value,
            'submitted_at' => '2026-05-15 09:00:00',
        ]);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-SUSTAIN', 'status' => RequisitionStatus::Approved->value]);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-CHANGE', 'status' => RequisitionStatus::ChangesRequested->value]);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-WITHDRAWN', 'status' => RequisitionStatus::Withdrawn->value]);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-CANCELLED', 'status' => RequisitionStatus::Cancelled->value]);
        $this->assertDatabaseHas('requisitions', ['number' => 'REQ-2026-DRAFT-DEMO', 'status' => RequisitionStatus::Draft->value]);
        $this->assertDatabaseHas('vendors', ['name' => 'Atlas Office Supplies', 'status' => 'preferred']);
        $this->assertDatabaseHas('vendors', ['name' => 'Northstar Furniture Co', 'status' => 'evaluation']);
        $this->assertDatabaseHas('vendors', ['name' => 'SecureWorks Advisory', 'status' => 'preferred']);
        $this->assertDatabaseHas('vendors', ['name' => 'Papertrail Logistics', 'status' => 'restricted']);
        $this->assertDatabaseHas('vendors', ['name' => 'ByteForge Systems', 'status' => 'evaluation']);
        $this->assertDatabaseHas('vendors', ['name' => 'Greenline Facilities', 'status' => 'preferred']);
        $this->assertDatabaseHas('vendors', ['name' => 'Harbor Industrial Supply', 'status' => 'preferred']);
        $this->assertDatabaseHas('vendors', ['name' => 'MetroFleet Services', 'status' => 'evaluation']);
        $this->assertDatabaseHas('vendors', ['name' => 'Civic Safety Partners', 'status' => 'restricted']);
        $this->assertDatabaseHas('procurement_projects', ['number' => 'PRJ-2026-0001', 'name' => 'HQ Workplace Refresh']);
        $this->assertDatabaseHas('procurement_projects', ['number' => 'PRJ-2026-1001', 'name' => 'Northwind Warehouse Launch']);
        $this->assertDatabaseHas('procurement_projects', ['number' => 'PRJ-2026-SUSTAIN', 'name' => 'Sustainable Office Expansion 2026']);
        $this->assertDatabaseHas('rfqs', ['number' => 'RFQ-2026-0001', 'title' => 'Office furniture package']);
        $this->assertDatabaseHas('rfqs', ['number' => 'RFQ-2026-1001', 'title' => 'Warehouse supply bundle']);
        $this->assertDatabaseHas('rfqs', ['number' => 'RFQ-2026-SUSTAIN', 'status' => RfqStatus::Open->value]);
        $this->assertDatabaseHas('rfqs', ['number' => 'RFQ-2026-DRAFT', 'status' => RfqStatus::Draft->value]);
        $this->assertDatabaseHas('rfqs', ['number' => 'RFQ-2026-CANCELLED', 'status' => RfqStatus::Cancelled->value]);
        $this->assertDatabaseHas('quotations', ['number' => 'QUO-2026-0001', 'status' => 'received']);
        $this->assertDatabaseHas('quotations', ['number' => 'QUO-2026-1001', 'status' => 'received']);
        $this->assertDatabaseHas('quotations', ['number' => 'QUO-2026-SUSTAIN-G', 'status' => 'received']);
        $this->assertDatabaseHas('quotations', ['number' => 'QUO-2026-SUSTAIN-N', 'status' => 'received']);
        $this->assertDatabaseHas('quotations', ['number' => 'QUO-2026-SUSTAIN-A', 'status' => 'received']);
        $this->assertDatabaseHas('sourcing_intake_reviews', ['status' => 'open', 'category' => 'IT Hardware']);
        $this->assertDatabaseHas('sourcing_intake_reviews', ['status' => 'ready_for_rfq', 'sourcing_path' => 'needs_rfq']);
        $this->assertDatabaseHas('sourcing_intake_reviews', ['status' => SourcingIntakeStatus::InReview->value]);
        $this->assertDatabaseHas('sourcing_intake_reviews', ['status' => SourcingIntakeStatus::ClarificationRequested->value]);
        $this->assertDatabaseHas('sourcing_intake_reviews', ['status' => SourcingIntakeStatus::DirectAwardRecorded->value]);
        $this->assertDatabaseHas('sourcing_intake_reviews', ['status' => SourcingIntakeStatus::Closed->value]);
        $this->assertDatabaseHas('approval_tasks', ['title' => 'Approve REQ-2026-0003', 'status' => 'active']);
        $this->assertDatabaseHas('approval_tasks', ['title' => 'Approve REQ-2026-0001', 'status' => 'active']);
        $this->assertDatabaseHas('approval_tasks', ['title' => 'Approve REQ-2026-1001', 'status' => 'approved']);
        $this->assertDatabaseHas('approval_tasks', ['title' => 'Approve REQ-2026-1002', 'status' => 'rejected']);
        $this->assertDatabaseHas('approval_tasks', ['title' => 'Sustainability compliance review', 'status' => 'changes_requested']);
        $this->assertDatabaseHas('approval_tasks', ['title' => 'Final award approval for RFQ-2026-SUSTAIN', 'status' => 'approved']);
        $this->assertDatabaseHas('approval_policies', ['tenant_id' => $acme->id, 'name' => 'Demo purchase order approval policy', 'subject_type' => 'purchase_order']);
        $this->assertDatabaseHas('approval_tasks', ['tenant_id' => $acme->id, 'title' => 'Review PO-2026-SUSTAIN-IN-REVIEW', 'status' => 'active']);
        $this->assertDatabaseHas('approval_tasks', ['tenant_id' => $acme->id, 'title' => 'Review PO-2026-SUSTAIN-CHANGES', 'status' => 'changes_requested']);
        $this->assertDatabaseHas('approval_tasks', ['tenant_id' => $acme->id, 'title' => 'Review PO-2026-SUSTAIN-APPROVED', 'status' => 'approved']);
        $this->assertDatabaseHas('approval_tasks', ['tenant_id' => $acme->id, 'title' => 'Review PO-2026-SUSTAIN-ISSUED', 'status' => 'approved']);
        $this->assertDatabaseHas('approval_tasks', ['tenant_id' => $acme->id, 'title' => 'Review PO-2026-SUSTAIN-ACK', 'status' => 'approved']);
        $this->assertDatabaseHas('approval_tasks', ['tenant_id' => $acme->id, 'title' => 'Review PO-2026-SUSTAIN-REJECTED', 'status' => 'rejected']);
        $this->assertDatabaseHas('awards', ['number' => 'AWD-2026-0001', 'status' => 'recommended']);
        $this->assertDatabaseHas('awards', ['number' => 'AWD-2026-1001', 'status' => 'recommended']);
        $this->assertDatabaseHas('quotation_normalizations', ['status' => QuotationNormalizationStatus::Approved->value]);
        $this->assertDatabaseHas('quotation_normalizations', ['status' => QuotationNormalizationStatus::ApprovedWithWarnings->value]);
        $this->assertDatabaseHas('quotation_normalizations', ['status' => QuotationNormalizationStatus::NeedsReview->value]);
        $this->assertDatabaseHas('quotation_normalizations', ['status' => QuotationNormalizationStatus::Failed->value]);
        $this->assertDatabaseHas('rfq_scorecards', ['template_name' => 'Sustainable Furniture Evaluation', 'status' => RfqScorecardStatus::Completed->value]);
        $this->assertDatabaseHas('rfq_award_recommendations', ['status' => RfqAwardRecommendationStatus::Draft->value]);
        $this->assertDatabaseHas('rfq_award_recommendations', ['status' => RfqAwardRecommendationStatus::PendingApproval->value]);
        $this->assertDatabaseHas('rfq_award_recommendations', ['status' => RfqAwardRecommendationStatus::ApprovalRouted->value]);
        $this->assertDatabaseHas('rfq_award_recommendations', ['status' => RfqAwardRecommendationStatus::Approved->value]);
        $this->assertDatabaseHas('purchase_order_request_handoffs', ['number' => 'POH-2026-SUSTAIN-READY', 'status' => PurchaseOrderRequestHandoffStatus::Ready->value]);
        $this->assertDatabaseHas('purchase_order_request_handoffs', ['number' => 'POH-2026-SUSTAIN-EXPORTED', 'status' => PurchaseOrderRequestHandoffStatus::Exported->value]);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-2026-SUSTAIN-DRAFT', 'status' => PurchaseOrderStatus::Draft->value]);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-2026-SUSTAIN-REVIEW', 'status' => PurchaseOrderStatus::ReadyForReview->value]);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-2026-SUSTAIN-IN-REVIEW', 'status' => PurchaseOrderStatus::InReview->value]);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-2026-SUSTAIN-CHANGES', 'status' => PurchaseOrderStatus::ChangesRequested->value]);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-2026-SUSTAIN-APPROVED', 'status' => PurchaseOrderStatus::Approved->value]);
        $this->assertDatabaseHas('purchase_orders', [
            'number' => 'PO-2026-SUSTAIN-ISSUED',
            'status' => PurchaseOrderStatus::Issued->value,
            'supplier_version_number' => 1,
            'last_supplier_export_format' => 'json',
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'number' => 'PO-2026-SUSTAIN-CO-PENDING',
            'status' => PurchaseOrderStatus::ChangePending->value,
            'supplier_version_number' => 1,
            'last_supplier_export_format' => 'json',
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'number' => 'PO-2026-SUSTAIN-CO-DELIVERY',
            'status' => PurchaseOrderStatus::Issued->value,
            'supplier_version_number' => 2,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'number' => 'PO-2026-SUSTAIN-ACK',
            'status' => PurchaseOrderStatus::Acknowledged->value,
            'supplier_version_number' => 2,
            'acknowledgement_reference' => 'ACK-PO-100',
        ]);
        $this->assertDatabaseHas('supplier_invoices', [
            'number' => 'INV-2026-DEMO-001',
            'status' => 'captured',
        ]);
        $this->assertDatabaseHas('supplier_invoices', [
            'number' => 'INV-2026-DEMO-002',
            'status' => 'in_review',
        ]);
        $this->assertDatabaseHas('supplier_invoices', [
            'number' => 'INV-2026-DEMO-003',
            'status' => 'needs_information',
        ]);
        $this->assertDatabaseHas('supplier_invoices', [
            'number' => 'INV-2026-DEMO-004',
            'status' => 'reviewed',
        ]);
        $inReviewInvoice = SupplierInvoice::query()->where('number', 'INV-2026-DEMO-002')->firstOrFail();
        $needsInformationInvoice = SupplierInvoice::query()->where('number', 'INV-2026-DEMO-003')->firstOrFail();
        $reviewedInvoice = SupplierInvoice::query()->where('number', 'INV-2026-DEMO-004')->firstOrFail();
        $this->assertSame('in_review', $inReviewInvoice->statusState()->value);
        $this->assertSame((string) $buyer->id, (string) $inReviewInvoice->review_started_by_user_id);
        $this->assertSame('2026-06-18 10:00:00', $inReviewInvoice->review_started_at?->toDateTimeString());
        $this->assertSame('needs_attention', data_get($inReviewInvoice->review_checklist, 'completeness.status'));
        $this->assertSame('Pending line-item reconciliation.', data_get($inReviewInvoice->review_checklist, 'completeness.note'));
        $this->assertSame('needs_information', $needsInformationInvoice->statusState()->value);
        $this->assertSame((string) $buyer->id, (string) $needsInformationInvoice->review_started_by_user_id);
        $this->assertSame('2026-06-19 10:00:00', $needsInformationInvoice->review_started_at?->toDateTimeString());
        $this->assertSame('Several line items need clarification with the supplier. Awaiting response.', $needsInformationInvoice->review_notes);
        $this->assertSame('fail', data_get($needsInformationInvoice->review_checklist, 'completeness.status'));
        $this->assertSame('Missing invoice line-item detail.', data_get($needsInformationInvoice->review_checklist, 'completeness.note'));
        $this->assertCount(3, $needsInformationInvoice->review_blockers);
        $this->assertSame('completeness', data_get($needsInformationInvoice->review_blockers, '0.key'));
        $this->assertSame('reviewed', $reviewedInvoice->statusState()->value);
        $this->assertSame((string) $finance->id, (string) $reviewedInvoice->review_started_by_user_id);
        $this->assertSame((string) $finance->id, (string) $reviewedInvoice->reviewed_by_user_id);
        $this->assertSame('2026-06-20 11:30:00', $reviewedInvoice->reviewed_at?->toDateTimeString());
        $this->assertSame('Invoice verified and approved for matching.', $reviewedInvoice->review_notes);
        $this->assertSame('pass', data_get($reviewedInvoice->review_checklist, 'completeness.status'));
        $this->assertSame([], $reviewedInvoice->review_blockers);
        $this->assertDatabaseHas('supplier_invoice_lines', [
            'line_number' => 1,
            'quantity_invoiced' => 50,
            'unit_price' => 400,
        ]);
        $this->assertDatabaseHas('supplier_invoice_lines', [
            'line_number' => 2,
            'quantity_invoiced' => 50,
            'unit_price' => 900,
        ]);
        $this->assertDatabaseHas('purchase_order_change_orders', [
            'number' => 'PO-2026-SUSTAIN-ACK-CO-001',
            'status' => PurchaseOrderChangeOrderStatus::Approved->value,
            'change_type' => PurchaseOrderChangeOrderType::Amendment->value,
            'material_change' => true,
            'requires_approval' => true,
            'supplier_version_number' => 2,
        ]);
        $this->assertDatabaseHas('purchase_order_change_orders', [
            'number' => 'PO-2026-SUSTAIN-CO-PENDING-CO-001',
            'status' => PurchaseOrderChangeOrderStatus::PendingApproval->value,
            'material_change' => true,
            'requires_approval' => true,
        ]);
        $this->assertDatabaseHas('purchase_order_change_orders', [
            'number' => 'PO-2026-SUSTAIN-CO-DELIVERY-CO-001',
            'status' => PurchaseOrderChangeOrderStatus::Approved->value,
            'material_change' => false,
            'requires_approval' => false,
            'supplier_version_number' => 2,
        ]);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-2026-SUSTAIN-REJECTED', 'status' => PurchaseOrderStatus::Rejected->value]);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-2026-SUSTAIN-CANCELLED', 'status' => PurchaseOrderStatus::Cancelled->value]);
        $issuedPurchaseOrder = PurchaseOrder::query()->where('number', 'PO-2026-SUSTAIN-ISSUED')->firstOrFail();
        $acknowledgedPurchaseOrder = PurchaseOrder::query()->where('number', 'PO-2026-SUSTAIN-ACK')->firstOrFail();
        $supplierInvoice = SupplierInvoice::query()->where('number', 'INV-2026-DEMO-001')->firstOrFail();
        $supplierInvoiceLines = SupplierInvoiceLine::query()->where('supplier_invoice_id', $supplierInvoice->id)->orderBy('line_number')->get();
        $invoiceAttachment = Attachment::query()->where('attachable_type', SupplierInvoice::class)->where('attachable_id', $supplierInvoice->id)->firstOrFail();
        $this->assertNotNull($issuedPurchaseOrder->approval_instance_id);
        $this->assertNotNull($acknowledgedPurchaseOrder->approval_instance_id);
        $this->assertSame($acme->id, $supplierInvoice->tenant_id);
        $this->assertSame($acme->id, $supplierInvoiceLines->firstOrFail()->tenant_id);
        $this->assertSame('captured', $supplierInvoice->statusState()->value);
        $this->assertSame('INV2026DEMO001', $supplierInvoice->invoice_number_normalized);
        $this->assertSame('65000.0000', $supplierInvoice->total_amount);
        $this->assertSame('50.0000', $supplierInvoiceLines->first()->quantity_invoiced);
        $this->assertSame('400.0000', $supplierInvoiceLines->first()->unit_price);
        $this->assertSame('900.0000', $supplierInvoiceLines->get(1)->unit_price);
        $this->assertSame('application/pdf', $invoiceAttachment->mime_type);
        $this->assertSame('inv-2026-demo-001.pdf', $invoiceAttachment->original_filename);
        $this->assertSame($acme->id, $invoiceAttachment->tenant_id);
        $this->assertSame('approved', data_get($issuedPurchaseOrder->supplier_version, 'approval.status'));
        $this->assertSame((string) $issuedPurchaseOrder->approval_instance_id, data_get($issuedPurchaseOrder->supplier_version, 'approval.approvalInstanceId'));
        $this->assertSame('approved', data_get($acknowledgedPurchaseOrder->supplier_version, 'approval.status'));
        $this->assertSame((string) $acknowledgedPurchaseOrder->approval_instance_id, data_get($acknowledgedPurchaseOrder->supplier_version, 'approval.approvalInstanceId'));
        $this->assertDatabaseHas('attachments', ['storage_disk' => 'local', 'original_filename' => 'office-refresh-brief.txt']);
        $this->assertDatabaseHas('attachments', ['storage_disk' => 'local', 'original_filename' => 'warehouse-supplies-brief.txt']);
        $this->assertDatabaseHas('attachments', ['storage_disk' => 'local', 'original_filename' => 'inv-2026-demo-001.pdf', 'mime_type' => 'application/pdf', 'previewable' => 0]);
        $this->assertSame('varchar', Schema::getColumnType('attachments', 'attachable_id'));

        $acmeOfficeRequisition = Requisition::query()->where('number', 'REQ-2026-0001')->firstOrFail();
        $requisitionAttachment = Attachment::query()
            ->where('attachable_type', Requisition::class)
            ->where('original_filename', 'office-refresh-brief.txt')
            ->firstOrFail();
        $this->assertSame($acmeOfficeRequisition->id, (int) $requisitionAttachment->attachable_id);
        $this->assertSame('Cognify local demo attachment for HQ workplace refresh.'."\n", Storage::disk($requisitionAttachment->storage_disk)->get($requisitionAttachment->storage_path));
        $this->assertDatabaseHas('audit_events', ['action' => 'requisition.submitted']);
        $this->assertDatabaseHas('notifications', ['title' => 'Local demo data is ready']);
        $this->assertDatabaseHas('demo_seed_runs', ['name' => 'local-demo']);

        $this->assertEqualsCanonicalizing(
            [
                RequisitionStatus::Approved->value,
                RequisitionStatus::Cancelled->value,
                RequisitionStatus::ChangesRequested->value,
                RequisitionStatus::Draft->value,
                RequisitionStatus::PendingApproval->value,
                RequisitionStatus::Rejected->value,
                RequisitionStatus::Submitted->value,
                RequisitionStatus::Withdrawn->value,
            ],
            Requisition::query()->distinct()->pluck('status')->map(fn (RequisitionStatus $status): string => $status->value)->all(),
        );
        $this->assertEqualsCanonicalizing(
            [
                RfqInvitationStatus::Pending->value,
                RfqInvitationStatus::Sent->value,
                RfqInvitationStatus::Acknowledged->value,
                RfqInvitationStatus::Declined->value,
                RfqInvitationStatus::Expired->value,
                RfqInvitationStatus::Cancelled->value,
            ],
            RfqInvitation::query()->distinct()->pluck('status')->map(fn (RfqInvitationStatus $status): string => $status->value)->all(),
        );
        $this->assertSame(9, DB::table('rfq_scorecard_entries')->count());
        $this->assertSame(2, CollaborationComment::query()->where('subject_type', Requisition::class)->whereHasMorph('subject', [Requisition::class], fn ($query) => $query->where('number', 'REQ-2026-SUSTAIN'))->count());
        $this->assertSame(2, ApprovalInstance::query()->where('subject_type', RfqAwardRecommendation::class)->count());
        $this->assertSame(11, PurchaseOrderRequestHandoff::query()->count());
        $this->assertSame(11, PurchaseOrder::query()->count());
        $this->assertSame(33, DB::table('purchase_order_lines')->count());
        $this->assertSame(3, PurchaseOrderChangeOrder::query()->count());
        $this->assertSame(3, DB::table('purchase_order_change_order_lines')->count());
        $this->assertSame(4, Shipment::query()->count());
        $this->assertSame(4, DB::table('shipment_lines')->count());
        $this->assertSame(5, FulfillmentTrackingEvent::query()->count());
        $this->assertDatabaseHas('shipments', ['number' => 'SH-2026-000001', 'status' => 'delivered']);
        $this->assertDatabaseHas('shipments', ['number' => 'SH-2026-000002', 'status' => 'in_transit']);
        $this->assertDatabaseHas('shipments', ['number' => 'SH-2026-000003', 'status' => 'delayed']);
        $this->assertDatabaseHas('shipments', ['number' => 'SH-2026-000004', 'status' => 'confirmed']);
        $this->assertDatabaseHas('fulfillment_tracking_events', ['status' => 'delivered', 'location' => 'Acme receiving dock']);
        $this->assertDatabaseHas('fulfillment_tracking_events', ['status' => 'delayed', 'location' => 'Johor distribution hub']);

        foreach (Attachment::query()->get() as $attachment) {
            $this->assertTrue(Storage::disk($attachment->storage_disk)->exists($attachment->storage_path));
        }

        $run = DemoSeedRun::query()->firstOrFail();

        $this->assertSame([
            'tenants' => 3,
            'users' => 7,
            'requisitions' => 10,
            'vendors' => 9,
            'projects' => 3,
            'rfqs' => 5,
            'quotations' => 5,
            'sourcing_intake_reviews' => 6,
            'approval_tasks' => 14,
            'awards' => 2,
            'quotation_normalizations' => 4,
            'quotation_scoring_templates' => 1,
            'rfq_scorecards' => 1,
            'purchase_order_request_handoffs' => 11,
            'purchase_orders' => 11,
            'shipments' => 4,
            'fulfillment_tracking_events' => 5,
            'supplier_invoices' => 24,
        ], $run->metadata);
        $this->assertSame(2, SupplierInvoiceException::query()->count());
        $this->assertSame('2026-05-15T09:00:00.000000Z', $run->seeded_at?->toJSON());
    }

    public function test_demo_tenant_seed_uses_slug_as_stable_lookup_key(): void
    {
        Tenant::query()->create([
            'slug' => 'acme',
            'name' => 'Old Acme Name',
        ]);

        $this->seed();

        $this->assertSame(3, Tenant::query()->count());
        $this->assertDatabaseHas('tenants', [
            'slug' => 'acme',
            'name' => 'Acme Procurement',
        ]);
        $this->assertDatabaseMissing('tenants', [
            'name' => 'Old Acme Name',
        ]);
    }

    public function test_approval_tasks_reject_non_model_subject_types(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Corp',
        ]);
        $admin = User::factory()->create();
        $tenant->users()->attach($admin->id, ['role' => TenantRole::Admin->value]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Approval task subject type must be an Eloquent model.');

        ApprovalTask::query()->create([
            'tenant_id' => $tenant->id,
            'assignee_id' => $admin->id,
            'subject_type' => self::class,
            'subject_id' => 1,
            'title' => 'Invalid subject',
            'status' => 'active',
        ]);
    }
}
