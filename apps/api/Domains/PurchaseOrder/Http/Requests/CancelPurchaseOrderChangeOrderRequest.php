<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelPurchaseOrderChangeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
