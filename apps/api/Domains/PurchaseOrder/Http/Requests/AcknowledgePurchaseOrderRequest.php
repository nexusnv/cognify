<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class AcknowledgePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('acknowledgeSupplier', $purchaseOrder) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:0'],
            'acknowledgedContactName' => ['sometimes', 'nullable', 'string', 'max:160'],
            'acknowledgementReference' => ['sometimes', 'nullable', 'string', 'max:160'],
            'acknowledgementNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasEvidence = collect([
                $this->input('acknowledgedContactName'),
                $this->input('acknowledgementReference'),
                $this->input('acknowledgementNote'),
            ])->contains(fn ($value): bool => is_string($value) && trim($value) !== '');

            if (! $hasEvidence) {
                $validator->errors()->add('acknowledgementReference', 'Supplier acknowledgement requires contact, reference, or note evidence.');
            }
        });
    }
}
