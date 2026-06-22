<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplierCreditMemoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vendorId' => ['required', 'integer', 'exists:vendors,id'],
            'originalInvoiceId' => ['nullable', 'string', 'exists:supplier_invoices,id'],
            'vendorCreditMemoNumber' => ['required', 'string', 'max:255'],
            'creditDate' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotalAmount' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'taxAmount' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'freightAmount' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'totalAmount' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.lineNumber' => ['required', 'integer', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'lines.*.unitPrice' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'lines.*.taxCode' => ['nullable', 'string', 'max:50'],
            'lines.*.taxAmount' => ['nullable', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'lines.*.purchaseOrderLineId' => ['nullable', 'string', 'exists:purchase_order_lines,id'],
            'lines.*.originalInvoiceLineId' => ['nullable', 'string', 'exists:supplier_invoice_lines,id'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
