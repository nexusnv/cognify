<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequestHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $handoff = $this->route('handoff');

        return $handoff instanceof PurchaseOrderRequestHandoff
            && ($this->user()?->can('update', $handoff) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'requestedPoDate' => ['nullable', 'date'],
            'deliveryAttention' => ['nullable', 'string', 'max:255'],
            'financeNote' => ['nullable', 'string', 'max:5000'],
            'exportMemo' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
