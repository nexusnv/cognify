<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('update', $purchaseOrder) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'requestedPoDate' => ['nullable', 'date'],
            'expectedDeliveryDate' => ['nullable', 'date'],
            'billingName' => ['nullable', 'string', 'max:255'],
            'billingAddress' => ['nullable', 'array'],
            'shippingName' => ['nullable', 'string', 'max:255'],
            'shippingAddress' => ['nullable', 'array'],
            'deliveryAttention' => ['nullable', 'string', 'max:255'],
            'paymentTerms' => ['nullable', 'string', 'max:255'],
            'deliveryTerms' => ['nullable', 'string', 'max:255'],
            'buyerNote' => ['nullable', 'string', 'max:2000'],
            'financeNote' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mutableFields = [
                'requestedPoDate',
                'expectedDeliveryDate',
                'billingName',
                'billingAddress',
                'shippingName',
                'shippingAddress',
                'deliveryAttention',
                'paymentTerms',
                'deliveryTerms',
                'buyerNote',
                'financeNote',
            ];

            foreach ($mutableFields as $field) {
                if ($this->exists($field)) {
                    return;
                }
            }

            $validator->errors()->add('lockVersion', 'At least one editable purchase order field must be provided.');
        });
    }
}
