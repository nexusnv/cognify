<?php

namespace Domains\Invoice\Http\Requests;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class CaptureSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('captureInvoice', $purchaseOrder) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'invoiceNumber' => ['required', 'string', 'max:100'],
            'invoiceDate' => ['required', 'date'],
            'dueDate' => ['required', 'date', 'after_or_equal:invoiceDate'],
            'taxAmount' => ['nullable', 'numeric', 'gte:0'],
            'freightAmount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchaseOrderLineId' => ['required', 'string', 'uuid'],
            'lines.*.quantityInvoiced' => ['required', 'numeric', 'gt:0'],
            'lines.*.unitPrice' => ['required', 'numeric', 'gte:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
