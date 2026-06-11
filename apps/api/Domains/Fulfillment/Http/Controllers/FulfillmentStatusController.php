<?php

namespace Domains\Fulfillment\Http\Controllers;

use Domains\Fulfillment\Http\Resources\FulfillmentStatusResource;
use Domains\Fulfillment\Support\DeliveryStatusCalculator;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FulfillmentStatusController
{
    use AuthorizesRequests;

    public function __construct(private readonly DeliveryStatusCalculator $deliveryStatusCalculator) {}

    public function show(PurchaseOrder $purchaseOrder): FulfillmentStatusResource
    {
        $this->authorize('view', $purchaseOrder);

        return new FulfillmentStatusResource($this->deliveryStatusCalculator->calculate($purchaseOrder));
    }
}
