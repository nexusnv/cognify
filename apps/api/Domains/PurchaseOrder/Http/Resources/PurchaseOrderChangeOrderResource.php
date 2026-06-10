<?php

namespace Domains\PurchaseOrder\Http\Resources;

use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderChangeOrder
 */
class PurchaseOrderChangeOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $changeOrder = $this->resource;

        return [
            'id' => (string) $changeOrder->id,
            'purchaseOrderId' => (string) $changeOrder->purchase_order_id,
            'number' => $changeOrder->number,
            'status' => $changeOrder->statusState()->value,
            'changeType' => $changeOrder->typeState()->value,
            'reason' => $changeOrder->reason,
            'materialChange' => (bool) $changeOrder->material_change,
            'requiresApproval' => (bool) $changeOrder->requires_approval,
            'fromPurchaseOrderStatus' => $changeOrder->from_purchase_order_status,
            'toPurchaseOrderStatus' => $changeOrder->to_purchase_order_status,
            'before' => $changeOrder->before_snapshot ?? [],
            'after' => $changeOrder->after_snapshot ?? [],
            'delta' => $changeOrder->delta_snapshot ?? [],
            'supplierVersionNumber' => $changeOrder->supplier_version_number,
            'approvalInstanceId' => $changeOrder->approval_instance_id !== null ? (string) $changeOrder->approval_instance_id : null,
            'requestedAt' => $changeOrder->requested_at?->toISOString(),
            'submittedAt' => $changeOrder->submitted_at?->toISOString(),
            'approvedAt' => $changeOrder->approved_at?->toISOString(),
            'rejectedAt' => $changeOrder->rejected_at?->toISOString(),
            'cancelledAt' => $changeOrder->cancelled_at?->toISOString(),
            'lockVersion' => $changeOrder->lock_version,
            'lines' => $changeOrder->relationLoaded('lines')
                ? PurchaseOrderChangeOrderLineResource::collection($changeOrder->lines)->resolve()
                : [],
            'purchaseOrder' => $changeOrder->relationLoaded('purchaseOrder') && $changeOrder->purchaseOrder !== null
                ? new PurchaseOrderResource($changeOrder->purchaseOrder)
                : null,
        ];
    }
}
