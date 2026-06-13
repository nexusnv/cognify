<?php

namespace Domains\Fulfillment\Http\Resources;

use Domains\Fulfillment\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shipment
 */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'purchaseOrderId' => (string) $this->purchase_order_id,
            'number' => $this->number,
            'status' => $this->statusState()->value,
            'carrierName' => $this->carrier_name,
            'trackingReference' => $this->tracking_reference,
            'shipmentDate' => $this->shipment_date?->toDateString(),
            'estimatedArrivalDate' => $this->estimated_arrival_date?->toDateString(),
            'actualDeliveryDate' => $this->actual_delivery_date?->toDateString(),
            'notes' => $this->notes,
            'createdByUserId' => $this->created_by_user_id !== null ? (string) $this->created_by_user_id : null,
            'lines' => $this->relationLoaded('lines')
                ? ShipmentLineResource::collection($this->lines)->resolve()
                : [],
            'lockVersion' => $this->lock_version,
        ];
    }
}
