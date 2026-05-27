<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Illuminate\Foundation\Http\FormRequest;

class MarkPurchaseOrderRequestHandoffReadyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $handoff = $this->route('handoff');

        return $handoff instanceof PurchaseOrderRequestHandoff
            && ($this->user()?->can('markReady', $handoff) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
