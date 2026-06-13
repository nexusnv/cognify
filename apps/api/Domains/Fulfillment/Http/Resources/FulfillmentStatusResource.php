<?php

namespace Domains\Fulfillment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfillmentStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'purchaseOrderId' => $this['purchaseOrderId'],
            'overallStatus' => $this['overallStatus'],
            'isDelayed' => $this['isDelayed'],
            'lateDeliveryCount' => $this['lateDeliveryCount'],
            'totalLineCount' => $this['totalLineCount'],
            'deliveredLineCount' => $this['deliveredLineCount'],
            'shipmentCount' => $this['shipmentCount'],
            'lineSummaries' => $this['lineSummaries'],
        ];
    }
}
