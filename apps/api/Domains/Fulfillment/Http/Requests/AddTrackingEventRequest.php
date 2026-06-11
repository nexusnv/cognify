<?php

namespace Domains\Fulfillment\Http\Requests;

use Domains\Fulfillment\Models\Shipment;
use Domains\Fulfillment\States\FulfillmentTrackingEventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');

        return $shipment instanceof Shipment
            && ($this->user()?->can('addTrackingEvent', $shipment) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(static fn (FulfillmentTrackingEventStatus $status) => $status->value, FulfillmentTrackingEventStatus::cases()))],
            'occurredAt' => ['required', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
