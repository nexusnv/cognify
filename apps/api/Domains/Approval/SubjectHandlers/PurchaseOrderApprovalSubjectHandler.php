<?php

namespace Domains\Approval\SubjectHandlers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Domains\PurchaseOrder\Actions\MarkPurchaseOrderApprovalRouted;
use Domains\PurchaseOrder\Actions\MarkPurchaseOrderApproved;
use Domains\PurchaseOrder\Actions\MarkPurchaseOrderRejected;
use Domains\PurchaseOrder\Actions\RequestPurchaseOrderChanges;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class PurchaseOrderApprovalSubjectHandler implements ApprovalSubjectHandler
{
    public function __construct(
        private readonly MarkPurchaseOrderApprovalRouted $markRouted,
        private readonly MarkPurchaseOrderApproved $markApproved,
        private readonly MarkPurchaseOrderRejected $markRejected,
        private readonly RequestPurchaseOrderChanges $requestChanges,
    ) {}

    public function subjectType(): string
    {
        return 'purchase_order';
    }

    public function modelClass(): string
    {
        return PurchaseOrder::class;
    }

    public function buildContext(Model $subject): ApprovalContextData
    {
        assert($subject instanceof PurchaseOrder);
        $subject->loadMissing(['vendor', 'lines', 'handoff', 'currentChangeOrder.lines']);
        $changeOrder = $subject->currentChangeOrder;
        $effectiveAmount = $changeOrder instanceof PurchaseOrderChangeOrder && $subject->statusState()->value === 'change_pending'
            ? (float) data_get($changeOrder->after_snapshot, 'totalAmount', $subject->total_amount)
            : (float) $subject->total_amount;
        $effectiveCurrency = $changeOrder instanceof PurchaseOrderChangeOrder && $subject->statusState()->value === 'change_pending'
            ? data_get($changeOrder->after_snapshot, 'currency', $subject->currency)
            : $subject->currency;

        return new ApprovalContextData(
            tenantId: (string) $subject->tenant_id,
            subjectType: 'purchase_order',
            requisitionId: $subject->requisition_id !== null ? (string) $subject->requisition_id : null,
            requesterId: $subject->handoff?->requested_by_user_id !== null ? (string) $subject->handoff->requested_by_user_id : null,
            amount: $effectiveAmount,
            currency: $effectiveCurrency,
            department: data_get($subject->source_snapshot, 'requisition.department'),
            costCenter: data_get($subject->source_snapshot, 'requisition.costCenter'),
            projectId: $subject->project_id !== null ? (string) $subject->project_id : null,
            lineItemCategories: $subject->lines->pluck('category')->filter()->map(fn ($value): string => (string) $value)->unique()->values()->all(),
            riskClassification: data_get($subject->approval_snapshot, 'riskClassification'),
            vendorId: $subject->vendor_id !== null ? (string) $subject->vendor_id : null,
            awardRecommendationId: $subject->rfq_award_recommendation_id !== null ? (string) $subject->rfq_award_recommendation_id : null,
            rfqId: $subject->rfq_id !== null ? (string) $subject->rfq_id : null,
            rfqNumber: data_get($subject->source_snapshot, 'rfq.number'),
            recommendedVendorId: $subject->vendor_id !== null ? (string) $subject->vendor_id : null,
            recommendedVendorName: $subject->vendor?->name ?? data_get($subject->source_snapshot, 'vendor.name'),
            recommendedQuotationId: $subject->quotation_id !== null ? (string) $subject->quotation_id : null,
            recommendedQuotationVersionId: $subject->quotation_version_id !== null ? (string) $subject->quotation_version_id : null,
            recommendedAmount: $effectiveAmount,
            recommendedCurrency: $effectiveCurrency,
            scorecardId: data_get($subject->approval_snapshot, 'scorecardId'),
            scorecardWeightedTotal: is_numeric(data_get($subject->approval_snapshot, 'scorecardWeightedTotal'))
                ? (float) data_get($subject->approval_snapshot, 'scorecardWeightedTotal')
                : null,
            riskSummaryPresent: filled(data_get($subject->approval_snapshot, 'riskSummary')),
            exceptionSummaryPresent: filled(data_get($subject->approval_snapshot, 'exceptionSummary')),
        );
    }

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
    {
        assert($subject instanceof PurchaseOrder);
        $subject->loadMissing(['vendor', 'currentChangeOrder']);
        $changeOrder = $subject->currentChangeOrder;
        $isChangePending = $subject->statusState()->value === 'change_pending' && $changeOrder instanceof PurchaseOrderChangeOrder;

        return new ApprovalSubjectSummary(
            type: 'purchase_order',
            id: (string) $subject->id,
            number: $subject->number,
            title: $isChangePending
                ? 'Review change order '.$changeOrder->number.' for purchase order '.$subject->number
                : 'Purchase order '.$subject->number,
            status: $subject->statusState()->value,
            primaryParty: $subject->vendor?->name ?? data_get($subject->source_snapshot, 'vendor.name'),
            amount: $isChangePending ? (float) data_get($changeOrder->after_snapshot, 'totalAmount', $subject->total_amount) : (float) $subject->total_amount,
            currency: $isChangePending ? (string) data_get($changeOrder->after_snapshot, 'currency', $subject->currency) : $subject->currency,
            href: "/purchase-orders/{$subject->id}",
            metadata: [
                'purchaseOrderId' => (string) $subject->id,
                'purchaseOrderNumber' => $subject->number,
                'vendorId' => $subject->vendor_id !== null ? (string) $subject->vendor_id : null,
                'vendorName' => $subject->vendor?->name ?? data_get($subject->source_snapshot, 'vendor.name'),
                'rfqId' => $subject->rfq_id !== null ? (string) $subject->rfq_id : null,
                'rfqNumber' => data_get($subject->source_snapshot, 'rfq.number'),
                'paymentTerms' => $subject->payment_terms,
                'deliveryTerms' => $subject->delivery_terms,
                ...($isChangePending ? [
                    'changeOrderId' => (string) $changeOrder->id,
                    'changeOrderNumber' => $changeOrder->number,
                    'changeType' => $changeOrder->typeState()->value,
                    'materialChange' => $changeOrder->material_change,
                    'totalDelta' => data_get($changeOrder->delta_snapshot, 'totalAmount'),
                    'reason' => $changeOrder->reason,
                ] : []),
            ],
        );
    }

    public function taskTitle(Model $subject): string
    {
        assert($subject instanceof PurchaseOrder);
        $subject->loadMissing('currentChangeOrder');

        if ($subject->statusState()->value === 'change_pending' && $subject->currentChangeOrder instanceof PurchaseOrderChangeOrder) {
            return 'Review change order '.$subject->currentChangeOrder->number.' for purchase order '.$subject->number;
        }

        return 'Review purchase order '.$subject->number;
    }

    public function notificationSubjectLabel(Model $subject): ?string
    {
        assert($subject instanceof PurchaseOrder);

        return $subject->number;
    }

    public function notificationBody(Model $subject): string
    {
        assert($subject instanceof PurchaseOrder);
        $subject->loadMissing('vendor');

        return 'Purchase order for '.($subject->vendor?->name ?? data_get($subject->source_snapshot, 'vendor.name') ?? $subject->number);
    }

    public function canDelegateTo(Model $subject, User $delegate): bool
    {
        return true;
    }

    public function delegationValidationMessage(Model $subject): string
    {
        return 'The selected delegate cannot approve this purchase order.';
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
        assert($subject instanceof PurchaseOrder);
        $this->markRouted->handle($subject, $instance, $actor);
    }

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof PurchaseOrder);
        $this->markApproved->handle($subject, $instance, $actor);
    }

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
    {
        assert($subject instanceof PurchaseOrder);
        $this->markRejected->handle($subject, $instance, $actor, $reason);
    }

    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        assert($subject instanceof PurchaseOrder);
        $this->requestChanges->handle($subject, $instance, $actor, $reason, $requestedFields);
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
