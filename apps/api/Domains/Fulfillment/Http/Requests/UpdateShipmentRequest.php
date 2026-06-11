<?php

namespace Domains\Fulfillment\Http\Requests;

use Domains\Fulfillment\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');

        return $shipment instanceof Shipment
            && ($this->user()?->can('updateShipment', $shipment) ?? false);
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'carrierName' => ['sometimes', 'nullable', 'string', 'max:200'],
            'trackingReference' => ['sometimes', 'nullable', 'string', 'max:200'],
            'shipmentDate' => ['sometimes', 'date'],
            'estimatedArrivalDate' => ['sometimes', 'nullable', 'date'],
            'actualDeliveryDate' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
