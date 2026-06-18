<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\Invoice\States\SupplierInvoiceStatus;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierInvoiceApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_stp_action_auto_approves_invoice_with_clean_match(): void
    {
        $invoice = $this->readyForApprovalInvoice(matchingStatus: 'matched');
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $evaluator = app(\Domains\Invoice\Actions\EvaluateStraightThroughProcessing::class);
        $result = $evaluator->handle($invoice->fresh(), $buyer);

        $this->assertTrue($result);
        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => SupplierInvoiceStatus::Approved->value,
            'stp_eligible' => true,
        ]);
        $this->assertNotNull(SupplierInvoice::find($invoice->id)->stp_processed_at);
    }

    public function test_evaluate_stp_action_auto_approves_invoice_with_all_exceptions_resolved_by_explanation(): void
    {
        $invoice = $this->readyForApprovalInvoice(
            matchingStatus: 'mismatch',
            withExplanationException: true,
        );
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $evaluator = app(\Domains\Invoice\Actions\EvaluateStraightThroughProcessing::class);
        $result = $evaluator->handle($invoice->fresh(), $buyer);

        $this->assertTrue($result);
        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => SupplierInvoiceStatus::Approved->value,
            'stp_eligible' => true,
        ]);
        $this->assertNotNull(SupplierInvoice::find($invoice->id)->stp_processed_at);
    }

    public function test_evaluate_stp_does_not_fire_when_value_adjustment_exists(): void
    {
        $invoice = $this->readyForApprovalInvoice(
            matchingStatus: 'mismatch',
            withValueAdjustment: true,
        );
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $evaluator = app(\Domains\Invoice\Actions\EvaluateStraightThroughProcessing::class);
        $result = $evaluator->handle($invoice->fresh(), $buyer);

        $this->assertFalse($result);
    }

    public function test_buyer_can_manually_submit_invoice_for_approval(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $approver = $this->tenantUser($tenant, TenantRole::Approver->value);
        $invoice = $this->readyForApprovalInvoice($tenant, false);
        $this->publishedApprovalPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/submit-approval", [
                'lockVersion' => $invoice->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_approval')
            ->assertJsonPath('data.approvalInstanceId', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_submit_requires_ready_for_approval_status(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $approver = $this->tenantUser($tenant, TenantRole::Approver->value);
        $invoice = $this->readyForApprovalInvoice($tenant, false);
        $this->publishedApprovalPolicy($tenant, $buyer, $approver);

        $invoice->forceFill(['status' => SupplierInvoiceStatus::InReview])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/submit-approval", [
                'lockVersion' => $invoice->lock_version,
            ])
            ->assertConflict();
    }

    public function test_submit_requires_current_lock_version(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $approver = $this->tenantUser($tenant, TenantRole::Approver->value);
        $invoice = $this->readyForApprovalInvoice($tenant, false);
        $this->publishedApprovalPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/submit-approval", [
                'lockVersion' => 999,
            ])
            ->assertConflict();
    }

    public function test_submit_without_matching_policy_returns_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $invoice = $this->readyForApprovalInvoice($tenant, false);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/submit-approval", [
                'lockVersion' => $invoice->lock_version,
            ])
            ->assertConflict();
    }

    public function test_cross_tenant_submit_is_denied(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $invoice = $this->readyForApprovalInvoice($tenant, false);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/submit-approval", [
                'lockVersion' => $invoice->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_approval_task_approve_marks_invoice_approved(): void
    {
        [$invoice, $task, $approver] = $this->submittedInvoiceForApproval();

        $this->actingAsTenant($invoice->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", [
                'lockVersion' => $task->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => SupplierInvoiceStatus::Approved->value,
            'approved_by_user_id' => $approver->id,
        ]);
    }

    public function test_approval_task_reject_marks_invoice_rejected(): void
    {
        [$invoice, $task, $approver] = $this->submittedInvoiceForApproval();

        $this->actingAsTenant($invoice->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/reject", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Invoice does not match the purchase order terms.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => SupplierInvoiceStatus::Rejected->value,
            'rejected_by_user_id' => $approver->id,
            'rejected_reason' => 'Invoice does not match the purchase order terms.',
        ]);
    }

    public function test_approval_task_request_changes_marks_invoice_changes_requested(): void
    {
        [$invoice, $task, $approver] = $this->submittedInvoiceForApproval();

        $this->actingAsTenant($invoice->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/request-changes", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Tax amount does not match the PO.',
                'requestedFields' => ['taxAmount'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => SupplierInvoiceStatus::NeedsInformation->value,
            'changes_requested_by_user_id' => $approver->id,
            'changes_requested_reason' => 'Tax amount does not match the PO.',
        ]);
    }

    public function test_supplier_invoice_approval_handler_is_registered(): void
    {
        $registry = app(\Domains\Approval\Services\ApprovalSubjectRegistry::class);
        $handler = $registry->forStoredSubject('supplier_invoice');

        $this->assertInstanceOf(
            \Domains\Approval\SubjectHandlers\SupplierInvoiceApprovalSubjectHandler::class,
            $handler,
        );
    }

    private function readyForApprovalInvoice(
        ?Tenant $tenant = null,
        bool $autoStp = true,
        bool $withValueAdjustment = false,
        bool $withExplanationException = false,
        string $matchingStatus = 'matched',
    ): SupplierInvoice {
        if ($tenant === null) {
            [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        } else {
            $buyer = $this->tenantUser($tenant, TenantRole::Buyer->value);
        }

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Traders',
            'status' => 'active',
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-2026-'.Str::random(6),
            'title' => 'Office supplies',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Monthly office supplies.',
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Office supplies',
                'description' => 'Monthly office supplies',
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
            'contact_email' => 'vendor@example.com',
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '10000.00',
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
            'currency' => 'MYR',
            'total_amount' => '10000.00',
            'line_items' => [[
                'lineNumber' => 1,
                'name' => 'Office supplies',
                'quantity' => '10',
                'unitOfMeasure' => 'set',
                'unitPrice' => '1000.00',
                'subtotal' => '10000.00',
            ]],
        ]);

        $recommendation = RfqAwardRecommendation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'recommended_vendor_id' => $vendor->id,
            'recommended_quotation_id' => $quotation->id,
            'recommended_quotation_version_id' => $version->id,
            'status' => \Domains\Quotation\States\RfqAwardRecommendationStatus::Approved,
            'rationale' => 'Best price.',
            'created_by_user_id' => $buyer->id,
            'updated_by_user_id' => $buyer->id,
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
            'number' => 'POH-2026-'.Str::random(6),
            'status' => PurchaseOrderRequestHandoffStatus::Ready,
            'currency' => 'MYR',
            'subtotal_amount' => '10000.00',
            'total_amount' => '10000.00',
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
            'number' => 'PO-'.Str::random(8),
            'status' => 'issued',
            'currency' => 'MYR',
            'subtotal_amount' => '10000.00',
            'tax_amount' => '0.00',
            'freight_amount' => '0.00',
            'discount_amount' => '0.00',
            'total_amount' => '10000.00',
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name]],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'approved_by_user_id' => $buyer->id,
            'approved_at' => now(),
            'lock_version' => 1,
        ]);

        PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'description' => 'Office supplies',
            'unit' => 'set',
            'quantity' => '10.0000',
            'unit_price' => '1000.0000',
            'subtotal_amount' => '10000.00',
            'total_amount' => '10000.00',
            'currency' => 'MYR',
        ]);

        $invoiceNumber = 'INV-'.Str::upper(Str::random(8));

        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'vendor_id' => $vendor->id,
            'number' => $invoiceNumber,
            'invoice_number' => $invoiceNumber,
            'invoice_number_normalized' => str_replace('-', '', $invoiceNumber),
            'status' => SupplierInvoiceStatus::ReadyForApproval->value,
            'invoice_date' => now()->toDateString(),
            'currency' => 'MYR',
            'subtotal_amount' => '10000.0000',
            'total_amount' => '10000.0000',
            'captured_by_user_id' => $buyer->id,
            'captured_at' => now(),
            'lock_version' => 1,
            'matching_status' => $matchingStatus,
        ]);

        $poLine = $po->lines->first();

        SupplierInvoiceLine::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_invoice_id' => $invoice->id,
            'purchase_order_line_id' => $poLine->id,
            'line_number' => 1,
            'description_snapshot' => 'Office supplies',
            'quantity_ordered' => '10.0000',
            'quantity_invoiced' => '10.0000',
            'unit_price' => '1000.0000',
            'line_subtotal' => '10000.0000',
        ]);

        if ($withValueAdjustment) {
            SupplierInvoiceException::query()->create([
                'tenant_id' => $tenant->id,
                'supplier_invoice_id' => $invoice->id,
                'purchase_order_line_id' => $poLine->id,
                'dimension' => 'unit_price',
                'status' => 'open',
                'match_type' => 'two_way_match',
                'expected_value' => '1000.0000',
                'actual_value' => '1050.0000',
                'resolution_type' => 'value_adjustment',
                'adjusted_value' => '1025.0000',
                'resolved_by_user_id' => $buyer->id,
                'resolved_at' => now(),
            ]);
        }

        if ($withExplanationException) {
            SupplierInvoiceException::query()->create([
                'tenant_id' => $tenant->id,
                'supplier_invoice_id' => $invoice->id,
                'purchase_order_line_id' => $poLine->id,
                'dimension' => 'unit_price',
                'status' => 'open',
                'match_type' => 'two_way_match',
                'expected_value' => '1000.0000',
                'actual_value' => '1005.0000',
                'resolution_type' => 'explanation',
                'explanation' => 'Minor price fluctuation due to market change.',
                'resolved_by_user_id' => $buyer->id,
                'resolved_at' => now(),
            ]);
        }

        return $invoice->fresh(['exceptions', 'tenant', 'purchaseOrder', 'purchaseOrder.vendor']);
    }

    private function publishedApprovalPolicy(Tenant $tenant, User $actor, User $approver): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Invoice approval',
            'description' => 'Approval policy for supplier invoices.',
            'subject_type' => 'supplier_invoice',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'supplier_invoice',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => 100,
            'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 1]],
            'route_template' => [
                'stages' => [[
                    'name' => 'Finance review',
                    'completionRule' => 'all',
                    'approvers' => [
                        ['type' => 'user', 'userId' => (string) $approver->id, 'label' => $approver->name],
                    ],
                    'fallbackApprovers' => [
                        ['type' => 'role', 'role' => 'approver', 'label' => 'Approver fallback'],
                    ],
                ]],
            ],
            'sla_rules' => [['stage' => 'Finance review', 'dueInHours' => 48]],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }

    private function submittedInvoiceForApproval(): array
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $approver = $this->tenantUser($tenant, TenantRole::Approver->value);
        $invoice = $this->readyForApprovalInvoice($tenant, false);
        $this->publishedApprovalPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/supplier-invoices/{$invoice->id}/submit-approval", [
                'lockVersion' => $invoice->lock_version,
            ])
            ->assertOk();

        $task = ApprovalTask::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('subject_type', SupplierInvoice::class)
            ->where('subject_id', $invoice->id)
            ->firstOrFail();

        return [$invoice->refresh(), $task, $approver, $buyer];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function tenantUser(Tenant $tenant, string $role): User
    {
        [, $user] = $this->tenantUserPair($role, $tenant);

        return $user;
    }

    private function tenantUserPair(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }
}
