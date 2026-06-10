<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPurchaseOrderChangeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
