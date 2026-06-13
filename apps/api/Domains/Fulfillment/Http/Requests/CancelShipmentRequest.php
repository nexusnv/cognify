<?php

namespace Domains\Fulfillment\Http\Requests;

use Domains\Fulfillment\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class CancelShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');

        return $shipment instanceof Shipment
            && ($this->user()?->can('cancel', $shipment) ?? false);
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
