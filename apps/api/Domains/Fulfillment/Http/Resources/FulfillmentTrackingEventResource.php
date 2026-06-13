<?php

namespace Domains\Fulfillment\Http\Resources;

use Domains\Fulfillment\Models\FulfillmentTrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FulfillmentTrackingEvent
 */
class FulfillmentTrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'shipmentId' => (string) $this->shipment_id,
            'status' => $this->statusState()->value,
            'occurredAt' => $this->occurred_at?->toISOString(),
            'location' => $this->location,
            'notes' => $this->notes,
            'createdByUserId' => $this->created_by_user_id !== null ? (string) $this->created_by_user_id : null,
        ];
    }
}
