<?php

namespace Domains\Fulfillment\Http\Requests;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class CreateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('createShipment', $purchaseOrder) ?? false);
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'carrierName' => ['sometimes', 'nullable', 'string', 'max:200'],
            'trackingReference' => ['sometimes', 'nullable', 'string', 'max:200'],
            'shipmentDate' => ['required', 'date'],
            'estimatedArrivalDate' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchaseOrderLineId' => ['required', 'string', 'uuid'],
            'lines.*.quantityShipped' => ['required', 'numeric', 'gt:0'],
            'lines.*.backorderQuantity' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'lines.*.backorderExpectedAt' => ['sometimes', 'nullable', 'date'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
