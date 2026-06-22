<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierCreditMemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'creditDate' => ['nullable', 'date'],
            'vendorCreditMemoNumber' => ['nullable', 'string', 'max:255'],
            'freightAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];
    }
}
