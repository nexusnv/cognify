<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCreditApplicationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'supplierInvoiceId' => ['required', 'string', 'exists:supplier_invoices,id'],
            'appliedAmount' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'applicationDate' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
