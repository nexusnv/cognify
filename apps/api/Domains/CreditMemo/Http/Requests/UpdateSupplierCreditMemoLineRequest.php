<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierCreditMemoLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'unitPrice' => ['nullable', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'taxCode' => ['nullable', 'string', 'max:50'],
            'taxAmount' => ['nullable', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
