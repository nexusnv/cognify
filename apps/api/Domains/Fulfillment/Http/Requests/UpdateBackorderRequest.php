<?php

namespace Domains\Fulfillment\Http\Requests;

use Domains\Fulfillment\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBackorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');

        return $shipment instanceof Shipment
            && ($this->user()?->can('updateBackorder', $shipment) ?? false);
    }

    public function rules(): array
    {
        return [
            'backorderQuantity' => ['required', 'numeric', 'gte:0'],
            'backorderExpectedAt' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
