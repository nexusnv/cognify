<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentImportRowRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'handoffNumber' => ['nullable', 'string', 'max:50'],
            'invoiceNumber' => ['nullable', 'string', 'max:255'],
            'allocatedAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'markFull' => ['nullable', 'boolean'],
            'settlementAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'settlementCurrency' => ['nullable', 'string', 'size:3'],
            'paidAt' => ['nullable', 'date'],
            'settlementMethod' => ['nullable', 'string', 'max:50'],
            'failureCode' => ['nullable', 'string', 'in:bank_rejected,insufficient_funds,vendor_blocked,system_error,other'],
            'failureReason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'voidReason' => ['nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
