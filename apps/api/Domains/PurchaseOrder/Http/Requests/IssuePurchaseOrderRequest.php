<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssuePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('issueToSupplier', $purchaseOrder) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['manual_email', 'portal_upload', 'external_system', 'manual_export'])],
            'supplierContactName' => ['sometimes', 'nullable', 'string', 'max:160'],
            'supplierContactEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
