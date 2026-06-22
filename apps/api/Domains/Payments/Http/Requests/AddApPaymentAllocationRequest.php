<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddApPaymentAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'supplierInvoiceId' => ['required', 'string', 'exists:supplier_invoices,id'],
            'allocatedAmount' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'allocationDate' => ['required', 'date'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'settlementAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'settlementCurrency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
