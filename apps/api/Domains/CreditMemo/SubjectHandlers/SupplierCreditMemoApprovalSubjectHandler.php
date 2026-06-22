<?php

namespace Domains\CreditMemo\SubjectHandlers;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SupplierCreditMemoApprovalSubjectHandler implements ApprovalSubjectHandler
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function subjectType(): string
    {
        return 'supplier_credit_memo';
    }

    public function modelClass(): string
    {
        return SupplierCreditMemo::class;
    }

    public function buildContext(Model $subject): ApprovalContextData
    {
        assert($subject instanceof SupplierCreditMemo);

        return new ApprovalContextData(
            tenantId: (string) $subject->tenant_id,
            subjectType: 'supplier_credit_memo',
            requisitionId: null,
            requesterId: null,
            amount: (float) ($subject->total_amount ?? 0),
            currency: $subject->currency,
            department: null,
            costCenter: null,
            projectId: null,
            lineItemCategories: [],
            riskClassification: null,
            vendorId: $subject->vendor_id !== null ? (string) $subject->vendor_id : null,
        );
    }

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->loadMissing(['vendor', 'exceptions']);

        $vendorName = $subject->vendor?->name ?? 'Unknown vendor';

        return new ApprovalSubjectSummary(
            type: 'supplier_credit_memo',
            id: (string) $subject->id,
            number: $subject->number,
            title: "Approve credit memo {$subject->number} from {$vendorName}",
            status: $subject->statusState()->value,
            primaryParty: $vendorName,
            amount: (float) ($subject->total_amount ?? 0),
            currency: $subject->currency,
            href: "/accounts-payable/credit-memos/{$subject->id}",
            metadata: [
                'supplierCreditMemoId' => (string) $subject->id,
                'supplierCreditMemoNumber' => $subject->number,
                'vendorId' => $subject->vendor_id !== null ? (string) $subject->vendor_id : null,
                'vendorName' => $vendorName,
                'exceptionCount' => $subject->exceptions->count(),
                'originalInvoiceId' => $subject->original_invoice_id !== null ? (string) $subject->original_invoice_id : null,
            ],
        );
    }

    public function taskTitle(Model $subject): string
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->loadMissing('vendor');
        $vendorName = $subject->vendor?->name ?? 'Unknown vendor';

        return "Approve credit memo {$subject->number} from {$vendorName}";
    }

    public function notificationSubjectLabel(Model $subject): ?string
    {
        assert($subject instanceof SupplierCreditMemo);

        return $subject->number;
    }

    public function notificationBody(Model $subject): string
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->loadMissing('vendor');
        $vendorName = $subject->vendor?->name ?? 'Unknown vendor';

        return "Credit memo {$subject->number} for {$subject->total_amount} {$subject->currency} from {$vendorName} requires approval.";
    }

    public function canDelegateTo(Model $subject, User $delegate): bool
    {
        return true;
    }

    public function delegationValidationMessage(Model $subject): string
    {
        return 'The selected delegate cannot approve this credit memo.';
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
        assert($subject instanceof SupplierCreditMemo);
    }

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof SupplierCreditMemo);
        DB::transaction(function () use ($tenant, $subject, $actor): void {
            $lockedMemo = SupplierCreditMemo::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $subject->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMemo->statusState() !== SupplierCreditMemoStatus::Approved) {
                throw new ConflictHttpException('Credit memo must be in approved state to post.');
            }

            $before = $lockedMemo->only(['status', 'posted_by_user_id', 'posted_at', 'lock_version']);

            $lockedMemo->forceFill([
                'status' => SupplierCreditMemoStatus::Open,
                'posted_by_user_id' => $actor->id,
                'posted_at' => now(),
                'lock_version' => $lockedMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'supplier_credit_memo.posted',
                subject: $lockedMemo,
                metadata: [
                    'supplierCreditMemoId' => (string) $lockedMemo->id,
                    'supplierCreditMemoNumber' => $lockedMemo->number,
                ],
                before: $before,
                after: $lockedMemo->only(['status', 'posted_by_user_id', 'posted_at', 'lock_version']),
            ));
        });
    }

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
    {
        assert($subject instanceof SupplierCreditMemo);
        DB::transaction(function () use ($tenant, $subject, $actor, $reason): void {
            $lockedMemo = SupplierCreditMemo::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $subject->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $lockedMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'approval_instance_id', 'lock_version']);

            $lockedMemo->forceFill([
                'status' => SupplierCreditMemoStatus::Draft,
                'submitted_by_user_id' => null,
                'submitted_at' => null,
                'approval_instance_id' => null,
                'lock_version' => $lockedMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'supplier_credit_memo.rejected',
                subject: $lockedMemo,
                metadata: [
                    'supplierCreditMemoId' => (string) $lockedMemo->id,
                    'supplierCreditMemoNumber' => $lockedMemo->number,
                    'reason' => $reason,
                ],
                before: $before,
                after: $lockedMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'approval_instance_id', 'lock_version']),
            ));
        });
    }

    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        assert($subject instanceof SupplierCreditMemo);
        DB::transaction(function () use ($tenant, $subject, $actor, $reason, $requestedFields): void {
            $lockedMemo = SupplierCreditMemo::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $subject->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $lockedMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'approval_instance_id', 'lock_version']);

            $lockedMemo->forceFill([
                'status' => SupplierCreditMemoStatus::Draft,
                'submitted_by_user_id' => null,
                'submitted_at' => null,
                'approval_instance_id' => null,
                'lock_version' => $lockedMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'supplier_credit_memo.changes_requested',
                subject: $lockedMemo,
                metadata: [
                    'supplierCreditMemoId' => (string) $lockedMemo->id,
                    'supplierCreditMemoNumber' => $lockedMemo->number,
                    'reason' => $reason,
                    'requestedFields' => $requestedFields,
                ],
                before: $before,
                after: $lockedMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'approval_instance_id', 'lock_version']),
            ));
        });
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
