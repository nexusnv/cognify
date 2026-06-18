# Invoice Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Route supplier invoices through the shared Approval domain with Straight-Through Processing (STP), adding 4 new invoice states, a subject handler, and approval UI.

**Architecture:** Extend `SupplierInvoiceStatus` enum with 4 cases. Create `SupplierInvoiceApprovalSubjectHandler` implementing `ApprovalSubjectHandler`. Hook the STP gate into `RunPostResolutionMatching`'s `transitionToReadyForApproval`. Create 4 new Actions (EvaluateSTP, Submit, MarkApproved, MarkRejected, MarkChangesRequested). Extend `ApprovalContextData` with invoice-specific fields. Wire through controller, route, policy, OpenAPI spec, and generated client. Add frontend components: approval status panel, badge, queue columns, and MSW handlers.

**Tech Stack:** Laravel 11, PHP 8.3, PostgreSQL, Next.js 14, TypeScript, shadcn/ui, MSW, Orval (OpenAPI codegen)

---

### Task 1: Extend SupplierInvoiceStatus enum and create migration

**Files:**
- Modify: `apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php`
- Create: `apps/api/database/migrations/2026_06_17_000007_add_approval_fields_to_supplier_invoices.php`
- Test: `apps/api/tests/Feature/SupplierInvoiceApprovalApiTest.php` (will be created in Task 10)

- [ ] **Step 1: Add 4 new status cases to the enum**

```php
// apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php

enum SupplierInvoiceStatus: string
{
    case Captured = 'captured';
    case InReview = 'in_review';
    case NeedsInformation = 'needs_information';
    case Reviewed = 'reviewed';
    case Matched = 'matched';
    case Mismatch = 'mismatch';
    case ReadyForApproval = 'ready_for_approval';
    case InApproval = 'in_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
}
```

- [ ] **Step 2: Create migration for approval columns**

Run: `php artisan make:migration add_approval_fields_to_supplier_invoices`

```php
// apps/api/database/migrations/2026_06_17_000007_add_approval_fields_to_supplier_invoices.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->uuid('approval_instance_id')->nullable()->index();
            $table->foreign('approval_instance_id')
                ->references('id')
                ->on('approval_instances')
                ->nullOnDelete();

            $table->uuid('approval_submitted_by_user_id')->nullable();
            $table->timestamp('approval_submitted_at')->nullable();

            $table->uuid('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->uuid('rejected_by_user_id')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->uuid('changes_requested_by_user_id')->nullable();
            $table->timestamp('changes_requested_at')->nullable();
            $table->text('changes_requested_reason')->nullable();
            $table->json('changes_requested_fields')->nullable();

            $table->boolean('stp_eligible')->default(false);
            $table->timestamp('stp_processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropForeign(['approval_instance_id']);
            $table->dropColumn([
                'approval_instance_id',
                'approval_submitted_by_user_id',
                'approval_submitted_at',
                'approved_by_user_id',
                'approved_at',
                'rejected_by_user_id',
                'rejected_at',
                'rejected_reason',
                'changes_requested_by_user_id',
                'changes_requested_at',
                'changes_requested_reason',
                'changes_requested_fields',
                'stp_eligible',
                'stp_processed_at',
            ]);
        });
    }
};
```

- [ ] **Step 3: Run migration**

Run: `php artisan migrate`

Expected: New columns added to `supplier_invoices` table.

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php \
  apps/api/database/migrations/2026_06_17_000007_add_approval_fields_to_supplier_invoices.php
git commit -m "feat(invoice): add approval statuses and migration columns"
```

---

### Task 2: Extend ApprovalContextData and ApprovalPolicyMatcher

**Files:**
- Modify: `apps/api/Domains/Approval/Data/ApprovalContextData.php`
- Modify: `apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php`
- Test: covered by Task 10

- [ ] **Step 1: Add 8 new fields to ApprovalContextData constructor**

```php
// In apps/api/Domains/Approval/Data/ApprovalContextData.php, add after existing fields:

public readonly ?string $supplierInvoiceId = null,
public readonly ?string $purchaseOrderId = null,
public readonly ?string $purchaseOrderNumber = null,
public readonly ?string $matchingStatus = null,
public readonly int $exceptionCount = 0,
public readonly bool $hasValueAdjustments = false,
public readonly float $originalInvoiceAmount = 0.0,
public readonly float $totalVarianceAdjusted = 0.0,
```

- [ ] **Step 2: Update `missingRequiredContext` supported fields array**

```php
// In missingRequiredContext(), add to $supportedFields:
'supplierInvoiceId',
'purchaseOrderId',
'purchaseOrderNumber',
'matchingStatus',
'exceptionCount',
'hasValueAdjustments',
'originalInvoiceAmount',
'totalVarianceAdjusted',
```

- [ ] **Step 3: Update `toArray()`**

```php
// Add to the returned array:
'supplierInvoiceId' => $this->supplierInvoiceId,
'purchaseOrderId' => $this->purchaseOrderId,
'purchaseOrderNumber' => $this->purchaseOrderNumber,
'matchingStatus' => $this->matchingStatus,
'exceptionCount' => $this->exceptionCount,
'hasValueAdjustments' => $this->hasValueAdjustments,
'originalInvoiceAmount' => $this->originalInvoiceAmount,
'totalVarianceAdjusted' => $this->totalVarianceAdjusted,
```

- [ ] **Step 4: Update `fromArray()`**

```php
// Add after existing mappings:
supplierInvoiceId: isset($context['supplierInvoiceId']) && $context['supplierInvoiceId'] !== '' ? (string) $context['supplierInvoiceId'] : null,
purchaseOrderId: isset($context['purchaseOrderId']) && $context['purchaseOrderId'] !== '' ? (string) $context['purchaseOrderId'] : null,
purchaseOrderNumber: isset($context['purchaseOrderNumber']) && $context['purchaseOrderNumber'] !== '' ? (string) $context['purchaseOrderNumber'] : null,
matchingStatus: isset($context['matchingStatus']) && $context['matchingStatus'] !== '' ? (string) $context['matchingStatus'] : null,
exceptionCount: (int) ($context['exceptionCount'] ?? 0),
hasValueAdjustments: (bool) ($context['hasValueAdjustments'] ?? false),
originalInvoiceAmount: isset($context['originalInvoiceAmount']) ? round((float) $context['originalInvoiceAmount'], 2) : 0.0,
totalVarianceAdjusted: isset($context['totalVarianceAdjusted']) ? round((float) $context['totalVarianceAdjusted'], 2) : 0.0,
```

- [ ] **Step 5: Add invoice field resolution to ApprovalPolicyMatcher::resolveFieldValue()**

```php
// In resolveFieldValue(), add before default:
'supplierInvoiceId' => $context->supplierInvoiceId,
'purchaseOrderId' => $context->purchaseOrderId,
'purchaseOrderNumber' => $context->purchaseOrderNumber,
'matchingStatus' => $context->matchingStatus,
'exceptionCount' => $context->exceptionCount,
'hasValueAdjustments' => $context->hasValueAdjustments,
'originalInvoiceAmount' => $context->originalInvoiceAmount,
'totalVarianceAdjusted' => $context->totalVarianceAdjusted,
```

- [ ] **Step 6: Update `evaluateRules()` missing-context tracking**

```php
// In evaluateRules(), add to the array of fields that trigger missingContextFields:
'in_array((string) ($rule['field'] ?? ''), [
    'riskClassification', 'vendorId', 'matchingStatus',
    'exceptionCount', 'hasValueAdjustments',
], true)'
```

- [ ] **Step 7: Commit**

```bash
git add apps/api/Domains/Approval/Data/ApprovalContextData.php \
  apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php
git commit -m "feat(approval): add invoice fields to context data and policy matcher"
```

---

### Task 3: Create EvaluateStraightThroughProcessing action

**Files:**
- Create: `apps/api/Domains/Invoice/Actions/EvaluateStraightThroughProcessing.php`
- Test: covered by Task 10

- [ ] **Step 1: Create the action file**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\States\SupplierInvoiceStatus;

class EvaluateStraightThroughProcessing
{
    public function __construct(
        private readonly MarkSupplierInvoiceApproved $markApproved,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * Evaluate STP eligibility and auto-advance if eligible.
     * Returns true if STP was applied (invoice moved to approved).
     * Returns false if not STP-eligible (caller should submit for approval).
     */
    public function handle(SupplierInvoice $invoice, User $actor): bool
    {
        $invoice->loadMissing('exceptions');

        if ($this->isStpEligible($invoice)) {
            $this->markApproved->handle(
                $invoice,
                actor: $actor,
                lockVersion: (int) $invoice->lock_version,
                isStp: true,
            );

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.stp_auto_approved',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'matchingStatus' => $invoice->matching_status,
                    'exceptionCount' => $invoice->exceptions->count(),
                ],
            ));

            return true;
        }

        return false;
    }

    private function isStpEligible(SupplierInvoice $invoice): bool
    {
        if (! $invoice->relationLoaded('exceptions')) {
            $invoice->load('exceptions');
        }

        $exceptions = $invoice->exceptions;

        // Clean match: matched status and zero exceptions ever created
        if ($invoice->matching_status === SupplierInvoiceStatus::Matched->value && $exceptions->isEmpty()) {
            return true;
        }

        // Explanation-resolved: all exceptions resolved with resolution_type = explanation
        if ($exceptions->isNotEmpty()) {
            $allResolved = $exceptions->every(fn ($e) => $e->resolved_at !== null);
            $allExplanation = $exceptions->every(fn ($e) => $e->resolution_type === 'explanation');
            $anyValueAdjustment = $exceptions->contains(fn ($e) => $e->resolution_type === 'value_adjustment');

            if ($allResolved && $allExplanation && ! $anyValueAdjustment) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 2: Run PHP lint to check syntax**

Run: `php -l apps/api/Domains/Invoice/Actions/EvaluateStraightThroughProcessing.php`

Expected: No syntax errors detected.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Invoice/Actions/EvaluateStraightThroughProcessing.php
git commit -m "feat(invoice): add Straight-Through Processing evaluation action"
```

---

### Task 4: Create Mark* invoice approval actions

**Files:**
- Create: `apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceApproved.php`
- Create: `apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceRejected.php`
- Create: `apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceChangesRequested.php`
- Test: covered by Task 10

- [ ] **Step 1: Create MarkSupplierInvoiceApproved**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;

class MarkSupplierInvoiceApproved
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        User $actor,
        int $lockVersion,
        bool $isStp = false,
    ): SupplierInvoice {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $isStp) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($isStp || $invoice->statusState() === SupplierInvoiceStatus::InApproval) {
                // allows transition from ready_for_approval (STP) or in_approval (task approval)
            } else {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                    'Supplier invoice can only be approved from in-approval or (STP) ready-for-approval status.',
                );
            }

            $invoice->assertLockVersion($lockVersion);

            $before = $invoice->only(['status', 'approved_by_user_id', 'approved_at', 'lock_version']);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Approved,
                'approved_by_user_id' => $isStp ? null : $actor->id,
                'approved_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.approved',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'isStp' => $isStp,
                ],
                before: $before,
                after: $invoice->only(['status', 'approved_by_user_id', 'approved_at', 'lock_version']),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
```

- [ ] **Step 2: Create MarkSupplierInvoiceRejected**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkSupplierInvoiceRejected
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        User $actor,
        int $lockVersion,
        string $reason,
    ): SupplierInvoice {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $reason) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::InApproval) {
                throw new ConflictHttpException('Supplier invoice can only be rejected from in-approval status.');
            }

            $invoice->assertLockVersion($lockVersion);

            $before = $invoice->only(['status', 'rejected_by_user_id', 'rejected_at', 'rejected_reason', 'lock_version']);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Rejected,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => now(),
                'rejected_reason' => $reason,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.rejected',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'reason' => $reason,
                ],
                before: $before,
                after: $invoice->only(['status', 'rejected_by_user_id', 'rejected_at', 'rejected_reason', 'lock_version']),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
```

- [ ] **Step 3: Create MarkSupplierInvoiceChangesRequested**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkSupplierInvoiceChangesRequested
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function handle(
        SupplierInvoice $supplierInvoice,
        User $actor,
        int $lockVersion,
        string $reason,
        array $requestedFields,
    ): SupplierInvoice {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $reason, $requestedFields) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::InApproval) {
                throw new ConflictHttpException('Supplier invoice changes can only be requested from in-approval status.');
            }

            $invoice->assertLockVersion($lockVersion);

            $before = $invoice->only([
                'status', 'changes_requested_by_user_id', 'changes_requested_at',
                'changes_requested_reason', 'changes_requested_fields', 'lock_version',
            ]);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::ChangesRequested,
                'changes_requested_by_user_id' => $actor->id,
                'changes_requested_at' => now(),
                'changes_requested_reason' => $reason,
                'changes_requested_fields' => $requestedFields,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            // Immediately transition to needs_information so AP user can correct
            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::NeedsInformation,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            // Invalidate cached matching results so P1-45 re-runs on re-entry
            $invoice->forceFill([
                'matching_status' => null,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.changes_requested',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'reason' => $reason,
                    'requestedFields' => $requestedFields,
                ],
                before: $before,
                after: $invoice->only([
                    'status', 'changes_requested_by_user_id', 'changes_requested_at',
                    'changes_requested_reason', 'changes_requested_fields', 'matching_status', 'lock_version',
                ]),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
```

- [ ] **Step 4: PHP lint all three files**

Run: `php -l apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceApproved.php && php -l apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceRejected.php && php -l apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceChangesRequested.php`

Expected: No syntax errors.

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceApproved.php \
  apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceRejected.php \
  apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceChangesRequested.php
git commit -m "feat(invoice): add approval outcome actions (approved/rejected/changes-requested)"
```

---

### Task 5: Create SubmitSupplierInvoiceForApproval action

**Files:**
- Create: `apps/api/Domains/Invoice/Actions/SubmitSupplierInvoiceForApproval.php`
- Test: covered by Task 10

- [ ] **Step 1: Create the action file**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\RouteSubjectForApproval;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitSupplierInvoiceForApproval
{
    public function __construct(
        private readonly RouteSubjectForApproval $routeSubject,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(SupplierInvoice $supplierInvoice, Tenant $tenant, User $actor, int $lockVersion): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $tenant, $actor, $lockVersion) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::ReadyForApproval) {
                throw new ConflictHttpException(
                    'Supplier invoice can only be submitted for approval from ready-for-approval status.',
                );
            }

            $invoice->assertLockVersion($lockVersion);

            // Delegate to shared Approval domain
            $instance = $this->routeSubject->handle($tenant, $actor, $invoice);

            $before = $invoice->only(['status', 'approval_instance_id', 'approval_submitted_by_user_id', 'approval_submitted_at', 'lock_version']);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::InApproval,
                'approval_instance_id' => $instance->id,
                'approval_submitted_by_user_id' => $actor->id,
                'approval_submitted_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.approval_submitted',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'approvalInstanceId' => (string) $instance->id,
                ],
                before: $before,
                after: $invoice->only(['status', 'approval_instance_id', 'approval_submitted_by_user_id', 'approval_submitted_at', 'lock_version']),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
```

- [ ] **Step 2: PHP lint**

Run: `php -l apps/api/Domains/Invoice/Actions/SubmitSupplierInvoiceForApproval.php`

Expected: No syntax errors.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Invoice/Actions/SubmitSupplierInvoiceForApproval.php
git commit -m "feat(invoice): add submit-for-approval action routing to shared Approval domain"
```

---

### Task 6: Create SupplierInvoiceApprovalSubjectHandler

**Files:**
- Create: `apps/api/Domains/Approval/SubjectHandlers/SupplierInvoiceApprovalSubjectHandler.php`
- Test: covered by Task 10

- [ ] **Step 1: Create the subject handler**

```php
<?php

namespace Domains\Approval\SubjectHandlers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Domains\Invoice\Actions\MarkSupplierInvoiceApproved;
use Domains\Invoice\Actions\MarkSupplierInvoiceChangesRequested;
use Domains\Invoice\Actions\MarkSupplierInvoiceRejected;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class SupplierInvoiceApprovalSubjectHandler implements ApprovalSubjectHandler
{
    public function __construct(
        private readonly MarkSupplierInvoiceApproved $markApproved,
        private readonly MarkSupplierInvoiceRejected $markRejected,
        private readonly MarkSupplierInvoiceChangesRequested $markChangesRequested,
    ) {}

    public function subjectType(): string
    {
        return 'supplier_invoice';
    }

    public function modelClass(): string
    {
        return SupplierInvoice::class;
    }

    public function buildContext(Model $subject): ApprovalContextData
    {
        assert($subject instanceof SupplierInvoice);
        $subject->loadMissing([
            'purchaseOrder',
            'purchaseOrder.vendor',
            'exceptions',
        ]);

        $originalAmount = (float) ($subject->total_amount ?? 0);
        $netPayableAmount = $this->calculateNetPayableAmount($subject, $originalAmount);

        return new ApprovalContextData(
            tenantId: (string) $subject->tenant_id,
            subjectType: 'supplier_invoice',
            requisitionId: null,
            requesterId: null,
            amount: $netPayableAmount,
            currency: $subject->currency,
            department: $subject->purchaseOrder?->department,
            costCenter: $subject->purchaseOrder?->cost_center,
            projectId: $subject->purchaseOrder?->project_id !== null
                ? (string) $subject->purchaseOrder->project_id : null,
            lineItemCategories: [],
            riskClassification: null,
            vendorId: $subject->purchaseOrder?->vendor_id !== null
                ? (string) $subject->purchaseOrder->vendor_id : null,
            supplierInvoiceId: (string) $subject->id,
            purchaseOrderId: $subject->purchase_order_id !== null
                ? (string) $subject->purchase_order_id : null,
            purchaseOrderNumber: $subject->purchaseOrder?->number,
            matchingStatus: $subject->matching_status,
            exceptionCount: $subject->exceptions->count(),
            hasValueAdjustments: $subject->exceptions
                ->contains(fn ($e) => $e->resolution_type === 'value_adjustment'),
            originalInvoiceAmount: $originalAmount,
            totalVarianceAdjusted: $originalAmount - $netPayableAmount,
        );
    }

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
    {
        assert($subject instanceof SupplierInvoice);
        $subject->loadMissing(['purchaseOrder.vendor']);

        $vendorName = $subject->purchaseOrder?->vendor?->name ?? $subject->vendor?->name ?? 'Unknown vendor';

        return new ApprovalSubjectSummary(
            type: 'supplier_invoice',
            id: (string) $subject->id,
            number: $subject->number,
            title: "Approve invoice {$subject->number} from {$vendorName}",
            status: $subject->statusState()->value,
            primaryParty: $vendorName,
            amount: (float) ($subject->total_amount ?? 0),
            currency: $subject->currency,
            href: "/accounts-payable/invoices/{$subject->id}",
            metadata: [
                'supplierInvoiceId' => (string) $subject->id,
                'supplierInvoiceNumber' => $subject->number,
                'purchaseOrderId' => $subject->purchase_order_id !== null ? (string) $subject->purchase_order_id : null,
                'purchaseOrderNumber' => $subject->purchaseOrder?->number,
                'vendorId' => $subject->purchaseOrder?->vendor_id !== null ? (string) $subject->purchaseOrder->vendor_id : null,
                'vendorName' => $vendorName,
                'matchingStatus' => $subject->matching_status,
                'exceptionCount' => $subject->exceptions->count(),
                'hasValueAdjustments' => $subject->exceptions->contains(fn ($e) => $e->resolution_type === 'value_adjustment'),
            ],
        );
    }

    public function taskTitle(Model $subject): string
    {
        assert($subject instanceof SupplierInvoice);
        $subject->loadMissing('purchaseOrder.vendor');
        $vendorName = $subject->purchaseOrder?->vendor?->name ?? $subject->vendor?->name ?? 'Unknown vendor';

        return "Approve invoice {$subject->number} from {$vendorName}";
    }

    public function notificationSubjectLabel(Model $subject): ?string
    {
        assert($subject instanceof SupplierInvoice);

        return $subject->number;
    }

    public function notificationBody(Model $subject): string
    {
        assert($subject instanceof SupplierInvoice);
        $subject->loadMissing('purchaseOrder.vendor');
        $vendorName = $subject->purchaseOrder?->vendor?->name ?? $subject->vendor?->name ?? 'Unknown vendor';

        return "Invoice {$subject->number} for {$subject->total_amount} {$subject->currency} from {$vendorName} requires approval.";
    }

    public function canDelegateTo(Model $subject, User $delegate): bool
    {
        return true;
    }

    public function delegationValidationMessage(Model $subject): string
    {
        return 'The selected delegate cannot approve this supplier invoice.';
    }

    public function escalationFallbackRecipients(Tenant $tenant, Model $subject, array $stageTemplate): iterable
    {
        $fallbackApprovers = collect($stageTemplate['fallbackApprovers'] ?? []);

        if ($fallbackApprovers->isEmpty()) {
            return $tenant->users()
                ->wherePivot('role', 'buyer')
                ->orderBy('users.id')
                ->get()
                ->merge(
                    $tenant->users()
                        ->wherePivot('role', 'admin')
                        ->orderBy('users.id')
                        ->get(),
                )
                ->unique('id')
                ->values();
        }

        return $fallbackApprovers
            ->flatMap(function (mixed $approver) use ($tenant): \Illuminate\Support\Collection {
                if (! is_array($approver)) {
                    return collect();
                }

                if (($approver['type'] ?? null) === 'user' && isset($approver['userId'])) {
                    $user = $tenant->users()->whereKey((int) $approver['userId'])->first();

                    return $user instanceof User ? collect([$user]) : collect();
                }

                if (($approver['type'] ?? null) === 'role' && isset($approver['role'])) {
                    return $tenant->users()
                        ->wherePivot('role', (string) $approver['role'])
                        ->orderBy('users.id')
                        ->get();
                }

                return collect();
            })
            ->unique('id')
            ->values();
    }

    public function onRouted(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof SupplierInvoice);
        // No additional routing side effects needed for invoices yet.
    }

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof SupplierInvoice);
        DB::transaction(function () use ($subject, $actor) {
            $lockedInvoice = SupplierInvoice::query()
                ->where('tenant_id', $subject->tenant_id)
                ->where('id', $subject->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->markApproved->handle($lockedInvoice, $actor, (int) $lockedInvoice->lock_version);
        });
    }

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
    {
        assert($subject instanceof SupplierInvoice);
        DB::transaction(function () use ($subject, $actor, $reason) {
            $lockedInvoice = SupplierInvoice::query()
                ->where('tenant_id', $subject->tenant_id)
                ->where('id', $subject->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->markRejected->handle($lockedInvoice, $actor, (int) $lockedInvoice->lock_version, $reason);
        });
    }

    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        assert($subject instanceof SupplierInvoice);
        DB::transaction(function () use ($subject, $actor, $reason, $requestedFields) {
            $lockedInvoice = SupplierInvoice::query()
                ->where('tenant_id', $subject->tenant_id)
                ->where('id', $subject->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->markChangesRequested->handle($lockedInvoice, $actor, (int) $lockedInvoice->lock_version, $reason, $requestedFields);
        });
    }

    private function calculateNetPayableAmount(SupplierInvoice $invoice, float $originalAmount): float
    {
        $adjustments = $invoice->exceptions
            ->where('resolution_type', 'value_adjustment');

        if ($adjustments->isEmpty()) {
            return $originalAmount;
        }

        $totalVariance = (float) $adjustments->sum(function ($exception): float {
            if ($exception->adjusted_value === null || $exception->expected_value === null) {
                return 0.0;
            }

            return (float) $exception->expected_value - (float) $exception->adjusted_value;
        });

        return max(0.0, $originalAmount - $totalVariance);
    }
}
```

- [ ] **Step 2: PHP lint**

Run: `php -l apps/api/Domains/Approval/SubjectHandlers/SupplierInvoiceApprovalSubjectHandler.php`

Expected: No syntax errors.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Approval/SubjectHandlers/SupplierInvoiceApprovalSubjectHandler.php
git commit -m "feat(approval): add SupplierInvoiceApprovalSubjectHandler"
```

---

### Task 7: Hook STP gate into RunPostResolutionMatching

**Files:**
- Modify: `apps/api/Domains/Invoice/Actions/RunPostResolutionMatching.php`
- Test: covered by Task 10

- [ ] **Step 1: Inject EvaluateStraightThroughProcessing and SubmitSupplierInvoiceForApproval into RunPostResolutionMatching**

```php
// In apps/api/Domains/Invoice/Actions/RunPostResolutionMatching.php

<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;

class RunPostResolutionMatching
{
    public function __construct(
        private readonly RunInvoiceMatching $matchingAction,
        private readonly CreateExceptionsFromMatchResults $exceptionCreator,
        private readonly AuditRecorder $auditRecorder,
        private readonly EvaluateStraightThroughProcessing $evaluateStp,
        private readonly SubmitSupplierInvoiceForApproval $submitForApproval,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function handle(SupplierInvoice $supplierInvoice, User $actor): void
    {
        DB::transaction(function () use ($supplierInvoice, $actor) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasValueAdjustment = SupplierInvoiceException::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->where('resolution_type', 'value_adjustment')
                ->exists();

            if ($hasValueAdjustment) {
                $updatedInvoice = $this->matchingAction->handle(
                    $invoice,
                    $actor,
                    (int) $invoice->lock_version,
                    'post_resolution',
                );

                if ($updatedInvoice->matching_status === SupplierInvoiceStatus::Mismatch->value) {
                    $this->exceptionCreator->handle($updatedInvoice);
                    return;
                }

                if ($updatedInvoice->matching_status === SupplierInvoiceStatus::Matched->value) {
                    $this->transitionToReadyForApproval($updatedInvoice, $actor);
                }
            } else {
                $this->transitionToReadyForApproval($invoice, $actor);
            }
        });
    }

    private function transitionToReadyForApproval(SupplierInvoice $invoice, User $actor): void
    {
        $before = $invoice->only(['status', 'lock_version']);
        $invoice->forceFill([
            'status' => SupplierInvoiceStatus::ReadyForApproval,
            'lock_version' => $invoice->lock_version + 1,
        ])->save();
        $after = $invoice->only(['status', 'lock_version']);

        $this->auditRecorder->record(new AuditEventData(
            tenant: $invoice->tenant,
            actor: $actor,
            action: 'supplier_invoice.ready_for_approval',
            subject: $invoice,
            after: $after,
            before: $before,
        ));

        // STP gate: evaluate within the same transaction
        $stpApplied = $this->evaluateStp->handle($invoice, $actor);

        if (! $stpApplied) {
            // Auto-submit non-STP invoices to approval domain
            $tenant = $this->currentTenant->get();
            if ($tenant !== null) {
                try {
                    $this->submitForApproval->handle($invoice, $tenant, $actor, (int) $invoice->lock_version);
                } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
                    // No matching policy — invoice stays ready_for_approval
                    // The error surfaces through the next user action
                    report($e);
                }
            }
        }
    }
}
```

- [ ] **Step 2: Update ResolveInvoiceException call site if needed**

The `ResolveInvoiceException` calls `RunPostResolutionMatching`. After this change, the STP gate will fire automatically as part of that call. No code change needed at the call site — the constructor signature changed, but Laravel's DI will resolve the new dependencies.

- [ ] **Step 3: PHP lint**

Run: `php -l apps/api/Domains/Invoice/Actions/RunPostResolutionMatching.php`

Expected: No syntax errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/Invoice/Actions/RunPostResolutionMatching.php
git commit -m "feat(invoice): hook STP gate and auto-submit into post-resolution matching"
```

---

### Task 8: Add controller, route, and policy for submit-approval

**Files:**
- Create: `apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceApprovalController.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/Domains/Invoice/Policies/SupplierInvoicePolicy.php`
- Test: covered by Task 10

- [ ] **Step 1: Create the approval controller**

```php
<?php

namespace Domains\Invoice\Http\Controllers;

use App\Auth\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Invoice\Actions\SubmitSupplierInvoiceForApproval;
use Domains\Invoice\Http\Resources\SupplierInvoiceResource;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierInvoiceApprovalController extends Controller
{
    public function __construct(
        private readonly SubmitSupplierInvoiceForApproval $submitAction,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function submit(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        $tenant = $this->currentTenant->getOrFail();
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        Gate::forUser($user)->authorize('submitForApproval', $supplierInvoice);

        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $invoice = $this->submitAction->handle(
                $supplierInvoice,
                $tenant,
                $user,
                (int) $validated['lockVersion'],
            );

            return response()->json([
                'data' => new SupplierInvoiceResource($invoice),
            ]);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'error' => ['message' => $e->getMessage()],
            ], 409);
        }
    }
}
```

- [ ] **Step 2: Add policy gate for submitForApproval**

```php
// In apps/api/Domains/Invoice/Policies/SupplierInvoicePolicy.php, add:

public function submitForApproval(User $user, SupplierInvoice $invoice): bool
{
    return $user->hasTenantRole($invoice->tenant_id, [TenantRole::Buyer, TenantRole::Admin]);
}
```

- [ ] **Step 3: Add route**

```php
// In apps/api/routes/api.php, after the existing supplier-invoice routes block:

Route::post('/supplier-invoices/{supplierInvoice}/submit-approval', [SupplierInvoiceApprovalController::class, 'submit']);
```

- [ ] **Step 4: Run route list to verify**

Run: `php artisan route:list --path=supplier-invoices`

Expected: Shows the new `POST /api/supplier-invoices/{supplierInvoice}/submit-approval` route.

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceApprovalController.php \
  apps/api/routes/api.php \
  apps/api/Domains/Invoice/Policies/SupplierInvoicePolicy.php
git commit -m "feat(invoice): add submit-approval controller, route, and policy gate"
```

---

### Task 9: Register handler in AppServiceProvider

**Files:**
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Test: covered by Task 10

- [ ] **Step 1: Add import and register handler**

```php
// Add import at top:
use Domains\Approval\SubjectHandlers\SupplierInvoiceApprovalSubjectHandler;

// In register(), add to the ApprovalSubjectRegistry constructor array:
$app->make(SupplierInvoiceApprovalSubjectHandler::class),
```

The updated singleton registration becomes:

```php
$this->app->singleton(ApprovalSubjectRegistry::class, fn ($app) => new ApprovalSubjectRegistry([
    $app->make(RequisitionApprovalSubjectHandler::class),
    $app->make(RfqAwardRecommendationApprovalSubjectHandler::class),
    $app->make(PurchaseOrderApprovalSubjectHandler::class),
    $app->make(SupplierInvoiceApprovalSubjectHandler::class),
]));
```

- [ ] **Step 2: Verify binding works**

Run: `php artisan tinker --execute="app(\Domains\Approval\Services\ApprovalSubjectRegistry::class)->forStoredSubject('supplier_invoice');"`

Expected: Returns instance of `SupplierInvoiceApprovalSubjectHandler`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/app/Providers/AppServiceProvider.php
git commit -m "feat(approval): register SupplierInvoiceApprovalSubjectHandler in service container"
```

---

### Task 10: Create API feature tests

**Files:**
- Create: `apps/api/tests/Feature/SupplierInvoiceApprovalApiTest.php`
- Test: `php artisan test --filter=SupplierInvoiceApproval`

- [ ] **Step 1: Create the test file**

```php
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
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierInvoiceApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    // --- STP tests ---

    public function test_stp_auto_approves_invoice_with_clean_match(): void
    {
        $invoice = $this->readyForApprovalInvoice();
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $invoice->tenant_id,
            'action' => 'supplier_invoice.stp_auto_approved',
        ]);

        // No approval tasks created
        $this->assertDatabaseMissing('approval_tasks', [
            'subject_type' => SupplierInvoice::class,
            'subject_id' => $invoice->id,
        ]);
    }

    public function test_stp_auto_approves_invoice_with_all_exceptions_resolved_by_explanation(): void
    {
        $invoice = $this->readyForApprovalInvoice(withExplanationException: true);
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $invoice->tenant_id,
            'action' => 'supplier_invoice.stp_auto_approved',
        ]);
    }

    public function test_stp_does_not_fire_when_value_adjustment_exists(): void
    {
        $invoice = $this->readyForApprovalInvoice(withValueAdjustment: true);
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => 'ready_for_approval',
        ]);
    }

    // --- Submit for approval tests ---

    public function test_non_stp_invoice_auto_submits_to_approval(): void
    {
        $invoice = $this->readyForApprovalInvoice(withValueAdjustment: true);
        $buyer = $this->tenantUser($invoice->tenant, TenantRole::Buyer->value);
        $approver = $this->tenantUser($invoice->tenant, TenantRole::Approver->value);
        $this->publishedApprovalPolicy($invoice->tenant, $buyer, $approver);

        // The invoice should have been auto-submitted after reaching ready_for_approval
        $invoice->refresh();

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => 'in_approval',
        ]);

        $this->assertDatabaseHas('approval_tasks', [
            'tenant_id' => $invoice->tenant_id,
            'subject_type' => SupplierInvoice::class,
            'subject_id' => $invoice->id,
            'assignee_id' => $approver->id,
            'status' => 'active',
        ]);
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

        // Set to a different status
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
            ->assertConflict()
            ->assertJsonPath('error.message', 'No approval policy versions are available.');
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

    // --- Approval action tests (via existing approval task endpoints) ---

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
            'status' => 'approved',
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
            'status' => 'rejected',
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
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_information');

        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => 'needs_information',
            'changes_requested_by_user_id' => $approver->id,
            'changes_requested_reason' => 'Tax amount does not match the PO.',
        ]);
    }

    public function test_changes_requested_invoice_re_enters_review_on_edit(): void
    {
        [$invoice, $task, $approver, $buyer] = $this->submittedInvoiceForApproval();

        // Request changes
        $this->actingAsTenant($invoice->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/request-changes", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Tax amount needs correction.',
                'requestedFields' => ['taxAmount'],
            ])
            ->assertOk();

        $invoice->refresh();

        // AP user edits the invoice (simulate capture update)
        $invoice->forceFill([
            'status' => SupplierInvoiceStatus::InReview,
            'lock_version' => $invoice->lock_version + 1,
        ])->save();

        // Completing review should go through reviewed -> matching -> ready_for_approval
        $invoice->forceFill([
            'status' => SupplierInvoiceStatus::Reviewed,
            'lock_version' => $invoice->lock_version + 1,
        ])->save();

        // The invoice is now in reviewed state — matching will transition to ready_for_approval
        // This simulates the re-entry path through review/matching/exception
        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'status' => 'reviewed',
        ]);
    }

    // --- Helper methods ---

    private function readyForApprovalInvoice(
        ?Tenant $tenant = null,
        bool $autoStp = true,
        bool $withValueAdjustment = false,
        bool $withExplanationException = false,
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

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'vendor_id' => $vendor->id,
            'number' => 'PO-'.Str::upper(Str::random(8)),
            'status' => 'issued',
            'currency' => 'MYR',
            'total_amount' => '10000.00',
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

        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'vendor_id' => $vendor->id,
            'number' => 'INV-'.Str::upper(Str::random(8)),
            'invoice_number' => 'INV-'.$tenant->id,
            'status' => $autoStp ? SupplierInvoiceStatus::ReadyForApproval : SupplierInvoiceStatus::ReadyForApproval,
            'currency' => 'MYR',
            'subtotal_amount' => '10000.0000',
            'total_amount' => '10000.0000',
            'captured_by_user_id' => $buyer->id,
            'captured_at' => now(),
            'lock_version' => 1,
            'matching_status' => $withValueAdjustment || $withExplanationException ? 'mismatch' : 'matched',
        ]);

        SupplierInvoiceLine::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_invoice_id' => $invoice->id,
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
                'purchase_order_line_id' => $po->lines->first()->id,
                'dimension' => 'unit_price',
                'result' => 'fail',
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
                'purchase_order_line_id' => $po->lines->first()->id,
                'dimension' => 'unit_price',
                'result' => 'fail',
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
```

- [ ] **Step 2: Run tests to verify they fail (TDD red phase)**

Run: `php artisan test --filter=SupplierInvoiceApprovalApiTest --stop-on-failure`

Expected: Some tests may pass (STP auto-approve for clean match happens via RunPostResolutionMatching which now triggers on creation). Others may fail as implementation is incomplete. Primary goal is to see the test harness works.

- [ ] **Step 3: Adjust test seeds to account for auto-behavior**

Note: The `readyForApprovalInvoice()` helper creates invoices already in `ready_for_approval` status. When the model is saved, the `saving` boot callback does NOT trigger STP evaluation (it's not a model event — STP fires from `RunPostResolutionMatching::transitionToReadyForApproval()`). For tests that need the invoice in `ready_for_approval` without STP firing, use `autoStp: false` (only available when passing a tenant) — but the `readyForApprovalInvoice` method creates the invoice directly in `ready_for_approval` so STP won't fire during creation. STP only fires when `RunPostResolutionMatching::handle()` is called. This is correct behavior.

- [ ] **Step 4: Run full test suite for the file**

Run: `php artisan test --filter=SupplierInvoiceApprovalApiTest --stop-on-failure`

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add apps/api/tests/Feature/SupplierInvoiceApprovalApiTest.php
git commit -m "test(invoice): add approval API feature tests with STP, submit, approve, reject, changes-requested"
```

---

### Task 11: Regenerate OpenAPI client

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json` (auto-generated)
- Modify: `packages/api-client/src/generated/` (auto-generated)

- [ ] **Step 1: Generate OpenAPI spec**

Run: `php artisan openapi:generate`

Expected: The new `/api/supplier-invoices/{supplierInvoice}/submit-approval` route appears in `apps/api/storage/openapi/openapi.json`.

- [ ] **Step 2: Regenerate TypeScript client**

Run: `pnpm generate:api`

Expected: New `submitSupplierInvoiceApproval` function and types in `packages/api-client/src/generated/`.

- [ ] **Step 3: Verify build**

Run: `pnpm --filter @cognify/api-client build`

Expected: Build succeeds with no type errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/storage/openapi/ packages/api-client/
git commit -m "feat(api): regenerate OpenAPI spec and API client for invoice approval"
```

---

### Task 12: Add seed and demo data

**Files:**
- Modify: existing seeders to include invoice approval demo data

- [ ] **Step 1: Add invoice approval demo data to the demo seeder**

Locate the demo seeder file (likely in `apps/api/database/seeders/` or `apps/api/Domains/Invoice/Database/Seeders/`). Add demo records for:

1. Invoice in `ready_for_approval` that is STP-eligible (clean match, zero exceptions) — auto-approves on seed refresh.
2. Invoice in `ready_for_approval` with value-adjustment exceptions — routes to approval automatically when a `supplier_invoice` approval policy is published.
3. Invoice in `in_approval` with an active approval task assigned to a demo approver.
4. Invoice in `approved` status via approval task outcome (with `approved_by_user_id` set).
5. Invoice in `approved` status via STP auto-advance (with `approved_by_user_id = null`).
6. Invoice in `rejected` status with reason.
7. Invoice in `needs_information` status from changes-requested with approver reason and requested fields.
8. Published approval policy with `subject_type = supplier_invoice` so the non-STP path works.

```php
// Example pattern for adding approval policy:
$policy = ApprovalPolicy::query()->create([
    'tenant_id' => $tenant->id,
    'name' => 'Invoice approval',
    'description' => 'Standard invoice approval workflow.',
    'subject_type' => 'supplier_invoice',
    'status' => ApprovalPolicyStatus::Active,
]);

ApprovalPolicyVersion::query()->create([
    'approval_policy_id' => $policy->id,
    'tenant_id' => $tenant->id,
    'subject_type' => 'supplier_invoice',
    'version_number' => 1,
    'status' => ApprovalPolicyVersionStatus::Published,
    'priority' => 100,
    'rules' => [],
    'route_template' => [
        'stages' => [[
            'name' => 'Finance review',
            'completionRule' => 'all',
            'approvers' => [],
            'fallbackApprovers' => [['type' => 'role', 'role' => 'approver']],
        ]],
    ],
]);
```

- [ ] **Step 2: Run seeders to verify**

Run: `php artisan db:seed --class=DemoSeeder` (or whatever the demo seeder class is)

Expected: No errors. Demo data includes invoice approval records.

- [ ] **Step 3: Commit**

```bash
git add apps/api/database/seeders/
git commit -m "feat(invoice): add invoice approval demo data and approval policy seed"
```

---

### Task 13: Add frontend fixtures and MSW handlers

**Files:**
- Modify: `apps/web/features/accounts-payable/mocks/accounts-payable-invoice-fixtures.ts`
- Modify: `apps/web/features/accounts-payable/mocks/accounts-payable-invoice-handlers.ts`
- Test: covered by Task 14

- [ ] **Step 1: Add approval fixtures**

Add to `accounts-payable-invoice-fixtures.ts` new invoice entries with approval states:

```typescript
// Add after existing fixtures:

export const invoiceInApprovalFixture: SupplierInvoice = {
  ...baseInvoice,
  id: 'invoice-in-approval-1',
  supplierInvoiceNumber: 'INV-2026-000042',
  status: SupplierInvoiceStatus.in_approval,
  approvalInstanceId: 'approval-instance-1',
  approvalSubmittedByUserId: 'user-buyer-1',
  approvalSubmittedAt: '2026-06-17T10:00:00Z',
};

export const invoiceApprovedFixture: SupplierInvoice = {
  ...baseInvoice,
  id: 'invoice-approved-1',
  supplierInvoiceNumber: 'INV-2026-000043',
  status: SupplierInvoiceStatus.approved,
  approvedByUserId: 'user-approver-1',
  approvedAt: '2026-06-17T11:00:00Z',
};

export const invoiceRejectedFixture: SupplierInvoice = {
  ...baseInvoice,
  id: 'invoice-rejected-1',
  supplierInvoiceNumber: 'INV-2026-000044',
  status: SupplierInvoiceStatus.rejected,
  rejectedByUserId: 'user-approver-1',
  rejectedAt: '2026-06-17T11:30:00Z',
  rejectedReason: 'Invoice does not match purchase order terms.',
};

export const invoiceChangesRequestedFixture: SupplierInvoice = {
  ...baseInvoice,
  id: 'invoice-changes-requested-1',
  supplierInvoiceNumber: 'INV-2026-000045',
  status: SupplierInvoiceStatus.needs_information,
  changesRequestedByUserId: 'user-approver-1',
  changesRequestedAt: '2026-06-17T12:00:00Z',
  changesRequestedReason: 'Tax amount needs correction.',
  changesRequestedFields: ['taxAmount'],
};

export const invoiceReadyForApprovalStpFixture: SupplierInvoice = {
  ...baseInvoice,
  id: 'invoice-stp-ready-1',
  supplierInvoiceNumber: 'INV-2026-000046',
  status: SupplierInvoiceStatus.ready_for_approval,
};

export const invoiceReadyForApprovalNonStpFixture: SupplierInvoice = {
  ...baseInvoice,
  id: 'invoice-stp-ready-non-stp-1',
  supplierInvoiceNumber: 'INV-2026-000047',
  status: SupplierInvoiceStatus.ready_for_approval,
};
```

- [ ] **Step 2: Add submit-approval MSW handler**

Add to `accounts-payable-invoice-handlers.ts`:

```typescript
import { http, HttpResponse } from 'msw';

// Add to the handlers array:
http.post('/api/supplier-invoices/:supplierInvoice/submit-approval', async ({ params, request }) => {
  const body = (await request.json()) as { lockVersion: number };
  const invoice = allFixtures.find((inv) => inv.id === params.supplierInvoice);

  if (!invoice) {
    return HttpResponse.json({ error: 'Not found' }, { status: 404 });
  }

  if (invoice.status !== SupplierInvoiceStatus.ready_for_approval) {
    return HttpResponse.json(
      { error: { message: 'Supplier invoice can only be submitted for approval from ready-for-approval status.' } },
      { status: 409 },
    );
  }

  if (invoice.lockVersion !== body.lockVersion) {
    return HttpResponse.json(
      { error: { message: 'Stale lock version.' } },
      { status: 409 },
    );
  }

  return HttpResponse.json({
    data: {
      ...invoice,
      status: SupplierInvoiceStatus.in_approval,
      approvalInstanceId: 'approval-instance-mock-1',
      approvalSubmittedByUserId: 'user-buyer-1',
      approvalSubmittedAt: new Date().toISOString(),
      lockVersion: invoice.lockVersion + 1,
    },
  });
}),
```

- [ ] **Step 3: Commit**

```bash
git add apps/web/features/accounts-payable/mocks/
git commit -m "feat(web): add invoice approval fixtures and MSW handler"
```

---

### Task 14: Create frontend hooks and components

**Files:**
- Create: `apps/web/features/accounts-payable/hooks/use-invoice-approval.ts`
- Create: `apps/web/features/accounts-payable/components/invoice-approval-status-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-approval-status-badge.tsx`
- Modify: `apps/web/features/accounts-payable/tables/accounts-payable-invoice-queue-table.tsx`
- Test: covered by Task 14

- [ ] **Step 1: Create use-invoice-approval hook**

```typescript
// apps/web/features/accounts-payable/hooks/use-invoice-approval.ts

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { submitSupplierInvoiceApproval } from '@cognify/api-client';
import { useToast } from '@cognify/ui/hooks/use-toast';

export function useInvoiceApproval(invoiceId: string) {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const submitMutation = useMutation({
    mutationFn: (lockVersion: number) =>
      submitSupplierInvoiceApproval(invoiceId, { lockVersion }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['supplier-invoices'] });
      toast({ title: 'Submitted for approval', variant: 'default' });
    },
    onError: (error: Error) => {
      toast({
        title: 'Failed to submit for approval',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  return {
    submitForApproval: submitMutation.mutate,
    isSubmitting: submitMutation.isPending,
    submitError: submitMutation.error,
  };
}
```

- [ ] **Step 2: Create InvoiceApprovalStatusBadge component**

```tsx
// apps/web/features/accounts-payable/components/invoice-approval-status-badge.tsx

import { Badge } from '@cognify/ui/components/badge';
import { SupplierInvoiceStatus } from '@cognify/api-client';
import type { SupplierInvoice } from '@cognify/api-client';

interface Props {
  status: SupplierInvoice['status'];
}

const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' | 'success' }> = {
  [SupplierInvoiceStatus.in_approval]: { label: 'In approval', variant: 'default' },
  [SupplierInvoiceStatus.approved]: { label: 'Approved', variant: 'success' },
  [SupplierInvoiceStatus.rejected]: { label: 'Rejected', variant: 'destructive' },
};

export function InvoiceApprovalStatusBadge({ status }: Props) {
  const config = statusConfig[status];
  if (!config) return null;

  return <Badge variant={config.variant}>{config.label}</Badge>;
}
```

- [ ] **Step 3: Create InvoiceApprovalStatusPanel component**

```tsx
// apps/web/features/accounts-payable/components/invoice-approval-status-panel.tsx

'use client';

import { Button } from '@cognify/ui/components/button';
import { Card, CardContent, CardHeader, CardTitle } from '@cognify/ui/components/card';
import { SupplierInvoiceStatus } from '@cognify/api-client';
import type { SupplierInvoice } from '@cognify/api-client';
import { InvoiceApprovalStatusBadge } from './invoice-approval-status-badge';
import { useInvoiceApproval } from '../hooks/use-invoice-approval';

interface Props {
  invoice: SupplierInvoice;
}

export function InvoiceApprovalStatusPanel({ invoice }: Props) {
  const { submitForApproval, isSubmitting } = useInvoiceApproval(invoice.id);

  if (invoice.status === SupplierInvoiceStatus.ready_for_approval) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Approval Status</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-muted-foreground">
            This invoice is ready for approval.
          </p>
          <Button
            onClick={() => submitForApproval(invoice.lockVersion)}
            disabled={isSubmitting}
          >
            {isSubmitting ? 'Submitting...' : 'Submit for Approval'}
          </Button>
        </CardContent>
      </Card>
    );
  }

  if (invoice.status === SupplierInvoiceStatus.in_approval) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Approval Status</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <InvoiceApprovalStatusBadge status={invoice.status} />
          <p className="text-sm text-muted-foreground">
            Submitted for approval on{' '}
            {invoice.approvalSubmittedAt
              ? new Date(invoice.approvalSubmittedAt).toLocaleDateString()
              : 'Unknown'}
          </p>
        </CardContent>
      </Card>
    );
  }

  if (invoice.status === SupplierInvoiceStatus.approved) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Approval Status</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <InvoiceApprovalStatusBadge status={invoice.status} />
          <p className="text-sm text-muted-foreground">
            Approved by {invoice.approvedByUserId ?? 'System (STP)'} on{' '}
            {invoice.approvedAt
              ? new Date(invoice.approvedAt).toLocaleDateString()
              : 'Unknown'}
          </p>
        </CardContent>
      </Card>
    );
  }

  if (invoice.status === SupplierInvoiceStatus.rejected) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Approval Status</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <InvoiceApprovalStatusBadge status={invoice.status} />
          <p className="text-sm text-muted-foreground">
            Rejected by {invoice.rejectedByUserId} on{' '}
            {invoice.rejectedAt
              ? new Date(invoice.rejectedAt).toLocaleDateString()
              : 'Unknown'}
          </p>
          {invoice.rejectedReason && (
            <p className="text-sm text-destructive">{invoice.rejectedReason}</p>
          )}
        </CardContent>
      </Card>
    );
  }

  if (invoice.status === SupplierInvoiceStatus.needs_information && invoice.changesRequestedReason) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Changes Requested</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <p className="text-sm text-muted-foreground">
            Changes requested by {invoice.changesRequestedByUserId} on{' '}
            {invoice.changesRequestedAt
              ? new Date(invoice.changesRequestedAt).toLocaleDateString()
              : 'Unknown'}
          </p>
          {invoice.changesRequestedReason && (
            <p className="text-sm font-medium">{invoice.changesRequestedReason}</p>
          )}
          {invoice.changesRequestedFields && invoice.changesRequestedFields.length > 0 && (
            <ul className="list-disc list-inside text-sm text-muted-foreground">
              {invoice.changesRequestedFields.map((field) => (
                <li key={field}>{field}</li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    );
  }

  return null;
}
```

- [ ] **Step 4: Extend the AP queue table with approval columns**

Modify `accounts-payable-invoice-queue-table.tsx` to:
1. Add `approvalStatus` column showing `InvoiceApprovalStatusBadge`
2. Add tab filters for `in_approval`, `approved`, `rejected`

```typescript
// Add to columns definition:
{
  accessorKey: 'status',
  header: 'Approval',
  cell: ({ row }) => <InvoiceApprovalStatusBadge status={row.original.status} />,
},

// Add to tab filters:
{ label: 'In Approval', value: SupplierInvoiceStatus.in_approval },
{ label: 'Approved', value: SupplierInvoiceStatus.approved },
{ label: 'Rejected', value: SupplierInvoiceStatus.rejected },
```

- [ ] **Step 5: Typecheck**

Run: `pnpm --filter @cognify/web typecheck`

Expected: No type errors.

- [ ] **Step 6: Commit**

```bash
git add apps/web/features/accounts-payable/hooks/ \
  apps/web/features/accounts-payable/components/ \
  apps/web/features/accounts-payable/tables/
git commit -m "feat(web): add invoice approval hooks, components, and queue extensions"
```

---

### Task 15: Run full verification

**Files:** All modified

- [ ] **Step 1: Run backend tests**

Run: `cd apps/api && php artisan test --filter=SupplierInvoiceApproval --stop-on-failure`

Expected: All tests pass.

- [ ] **Step 2: Run existing approval tests to confirm no regression**

Run: `cd apps/api && php artisan test --filter=ApprovalTask --stop-on-failure`

Expected: All existing approval tests still pass.

- [ ] **Step 3: Run frontend typecheck**

Run: `pnpm --filter @cognify/web typecheck`

Expected: No type errors.

- [ ] **Step 4: Run lint**

Run: `pnpm lint`

Expected: No lint errors.

- [ ] **Step 5: Run frontend tests**

Run: `pnpm --filter @cognify/web test -- accounts-payable`

Expected: All frontend tests pass.

- [ ] **Step 6: Run full build**

Run: `pnpm build`

Expected: Build succeeds.
