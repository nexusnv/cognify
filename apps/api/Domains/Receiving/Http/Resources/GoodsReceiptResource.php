<?php

namespace Domains\Receiving\Http\Resources;

use Domains\Receiving\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceipt
 */
class GoodsReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'purchaseOrderId' => (string) $this->purchase_order_id,
            'number' => $this->number,
            'status' => $this->statusState()->value,
            'receiptDate' => $this->receipt_date?->toDateString(),
            'receiptReference' => $this->receipt_reference,
            'notes' => $this->notes,
            'recordedByUserId' => $this->recorded_by_user_id !== null ? (string) $this->recorded_by_user_id : null,
            'recordedAt' => $this->recorded_at?->toISOString(),
            'requesterConfirmedByUserId' => $this->requester_confirmed_by_user_id !== null ? (string) $this->requester_confirmed_by_user_id : null,
            'requesterConfirmedAt' => $this->requester_confirmed_at?->toISOString(),
            'buyerConfirmedByUserId' => $this->buyer_confirmed_by_user_id !== null ? (string) $this->buyer_confirmed_by_user_id : null,
            'buyerConfirmedAt' => $this->buyer_confirmed_at?->toISOString(),
            'lines' => $this->relationLoaded('lines')
                ? GoodsReceiptLineResource::collection($this->lines)->resolve()
                : [],
            'lockVersion' => $this->lock_version,
        ];
    }
}
