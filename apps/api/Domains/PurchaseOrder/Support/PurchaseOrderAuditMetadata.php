<?php

namespace Domains\PurchaseOrder\Support;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;

class PurchaseOrderAuditMetadata
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function for(
        PurchaseOrder $purchaseOrder,
        ?PurchaseOrderRequestHandoff $handoff = null,
        array $extra = [],
    ): array {
        $purchaseOrder->loadMissing('handoff');
        $handoff ??= $purchaseOrder->handoff;

        return array_merge([
            'purchaseOrderId' => (string) $purchaseOrder->id,
            'purchaseOrderNumber' => $purchaseOrder->number,
            'handoffId' => $purchaseOrder->purchase_order_request_handoff_id !== null
                ? (string) $purchaseOrder->purchase_order_request_handoff_id
                : null,
            'handoffNumber' => $handoff?->number,
            'recommendationId' => $purchaseOrder->rfq_award_recommendation_id !== null
                ? (string) $purchaseOrder->rfq_award_recommendation_id
                : null,
            'vendorId' => $purchaseOrder->vendor_id !== null
                ? (string) $purchaseOrder->vendor_id
                : null,
            'totalAmount' => $purchaseOrder->total_amount !== null
                ? (string) $purchaseOrder->total_amount
                : null,
            'currency' => $purchaseOrder->currency,
        ], $extra);
    }
}
