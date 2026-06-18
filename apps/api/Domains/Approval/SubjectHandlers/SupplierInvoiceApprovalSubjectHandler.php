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
use Illuminate\Support\Collection;
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
            return $this->usersForRole($tenant, 'buyer')
                ->merge($this->usersForRole($tenant, 'admin'))
                ->unique('id')
                ->values();
        }

        return $fallbackApprovers
            ->flatMap(function (mixed $approver) use ($tenant): Collection {
                if (! is_array($approver)) {
                    return collect();
                }

                if (($approver['type'] ?? null) === 'user' && isset($approver['userId'])) {
                    $user = $tenant->users()->whereKey((int) $approver['userId'])->first();

                    return $user instanceof User ? collect([$user]) : collect();
                }

                if (($approver['type'] ?? null) === 'role' && isset($approver['role'])) {
                    return $this->usersForRole($tenant, (string) $approver['role']);
                }

                return collect();
            })
            ->unique('id')
            ->values();
    }

    public function onRouted(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof SupplierInvoice);
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

    /**
     * @return Collection<int, User>
     */
    private function usersForRole(Tenant $tenant, string $role): Collection
    {
        return $tenant->users()
            ->wherePivot('role', $role)
            ->orderBy('users.id')
            ->get();
    }
}
