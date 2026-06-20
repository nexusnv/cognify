<?php

namespace Domains\AccountsPayable\Http\Resources;

use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Payments\Http\Resources\ApPaymentAllocationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin ApPaymentHandoff
 */
class ApPaymentHandoffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allocations = $this->whenLoaded('allocations', fn () => $this->allocations, fn () => collect());
        $allocationsArray = $allocations->isNotEmpty()
            ? ApPaymentAllocationResource::collection($allocations)->resolve()
            : [];

        $allocationsByInvoice = $allocations->groupBy('supplier_invoice_id');

        $invoicesResource = ($this->invoices ?? collect())->map(function ($inv) use ($allocationsByInvoice) {
            $invAllocations = $allocationsByInvoice->get($inv->id, collect())
                ->whereNull('voided_at');
            $sum = '0.0000';
            foreach ($invAllocations as $alloc) {
                $sum = bcadd($sum, (string) $alloc->allocated_amount, 4);
            }
            $outstanding = bcsub((string) $inv->total_amount, $sum, 4);
            $status = bccomp($sum, (string) $inv->total_amount, 4) === 0
                ? 'paid'
                : (bccomp($sum, '0', 4) > 0 ? 'partially_paid' : 'payment_scheduled');

            return [
                'id' => (string) $inv->id,
                'invoiceNumber' => $inv->invoice_number,
                'currency' => $inv->currency,
                'totalAmount' => (string) $inv->total_amount,
                'allocatedAmount' => $sum,
                'outstandingAmount' => $outstanding,
                'paymentStatus' => $status,
            ];
        });

        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'status' => $this->statusState()->value,
            'effectivePaymentDate' => $this->effective_payment_date?->toDateString(),
            'notes' => $this->notes,
            'currency' => $this->currency,
            'totalAmount' => (string) $this->total_amount,
            'remittanceReference' => $this->remittance_reference,
            'scheduledAt' => $this->scheduled_at?->toISOString(),
            'scheduledByUserId' => $this->scheduled_by_user_id !== null ? (string) $this->scheduled_by_user_id : null,
            'scheduledForDate' => $this->scheduled_for_date?->toDateString(),
            'paymentReference' => $this->payment_reference,
            'paidAt' => $this->paid_at?->toISOString(),
            'paidByUserId' => $this->paid_by_user_id !== null ? (string) $this->paid_by_user_id : null,
            'remittanceAdviceSentAt' => $this->remittance_advice_sent_at?->toISOString(),
            'failedAt' => $this->failed_at?->toISOString(),
            'failedByUserId' => $this->failed_by_user_id !== null ? (string) $this->failed_by_user_id : null,
            'failureCode' => $this->failure_code,
            'failureReason' => $this->failure_reason,
            'voidedAt' => $this->voided_at?->toISOString(),
            'voidedByUserId' => $this->voided_by_user_id !== null ? (string) $this->voided_by_user_id : null,
            'voidReason' => $this->void_reason,
            'varianceAmount' => $this->variance_amount !== null ? (string) $this->variance_amount : null,
            'varianceReason' => $this->variance_reason,
            'varianceClosedAt' => $this->variance_closed_at?->toISOString(),
            'varianceClosedByUserId' => $this->variance_closed_by_user_id !== null ? (string) $this->variance_closed_by_user_id : null,
            'allocations' => $allocationsArray,
            'invoices' => $invoicesResource,
            'snapshot' => $this->snapshot,
            'readinessWarnings' => $this->readiness_warnings,
            'createdBy' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser?->id !== null ? (string) $this->createdByUser->id : null,
                'name' => $this->createdByUser?->name,
            ]),
            'readyBy' => $this->whenLoaded('readyByUser', fn () => [
                'id' => $this->readyByUser?->id !== null ? (string) $this->readyByUser->id : null,
                'name' => $this->readyByUser?->name,
            ]),
            'readyAt' => $this->ready_at?->toISOString(),
            'cancelledBy' => $this->whenLoaded('cancelledByUser', fn () => [
                'id' => $this->cancelledByUser?->id !== null ? (string) $this->cancelledByUser->id : null,
                'name' => $this->cancelledByUser?->name,
            ]),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelledReason' => $this->cancelled_reason,
            'lastExportedBy' => $this->whenLoaded('lastExportedByUser', fn () => [
                'id' => $this->lastExportedByUser?->id !== null ? (string) $this->lastExportedByUser->id : null,
                'name' => $this->lastExportedByUser?->name,
            ]),
            'lastExportedAt' => $this->last_exported_at?->toISOString(),
            'lastExportFormat' => $this->last_export_format,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $this->resource),
                'canMarkReady' => Gate::allows('markReady', $this->resource),
                'canCancel' => Gate::allows('cancel', $this->resource),
                'canExport' => Gate::allows('export', $this->resource),
                'canSchedule' => Gate::allows('schedule', $this->resource),
                'canAddAllocation' => Gate::allows('addAllocation', $this->resource),
                'canMarkPaid' => Gate::allows('markPaid', $this->resource),
                'canCloseWithVariance' => Gate::allows('closeWithVariance', $this->resource),
                'canMarkFailed' => Gate::allows('markFailed', $this->resource),
                'canVoid' => Gate::allows('void', $this->resource),
                'canReschedule' => Gate::allows('reschedule', $this->resource),
            ],
        ];
    }
}
