<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddSupplierCreditMemoLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'lineNumber' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'unitPrice' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'taxCode' => ['nullable', 'string', 'max:50'],
            'taxAmount' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'purchaseOrderLineId' => ['nullable', 'string', 'exists:purchase_order_lines,id'],
            'originalInvoiceLineId' => ['nullable', 'string', 'exists:supplier_invoice_lines,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
