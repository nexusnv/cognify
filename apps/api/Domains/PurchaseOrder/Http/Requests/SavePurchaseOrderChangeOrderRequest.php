<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePurchaseOrderChangeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'changeType' => ['required', Rule::in(['amendment', 'partial_cancellation', 'full_cancellation'])],
            'expectedDeliveryDate' => ['sometimes', 'nullable', 'date'],
            'requestedPoDate' => ['sometimes', 'nullable', 'date'],
            'billingName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billingAddress' => ['sometimes', 'nullable', 'array'],
            'shippingName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shippingAddress' => ['sometimes', 'nullable', 'array'],
            'deliveryAttention' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paymentTerms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'deliveryTerms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'buyerNote' => ['sometimes', 'nullable', 'string'],
            'financeNote' => ['sometimes', 'nullable', 'string'],
            'lines' => ['sometimes', 'array'],
            'lines.*.lineId' => ['required', 'string'],
            'lines.*.action' => ['required', Rule::in(['update', 'cancel'])],
            'lines.*.quantity' => ['sometimes', 'nullable', 'numeric'],
            'lines.*.unitPrice' => ['sometimes', 'nullable', 'numeric'],
            'lines.*.expectedDeliveryDate' => ['sometimes', 'nullable', 'date'],
            'lines.*.deliveryLocation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
