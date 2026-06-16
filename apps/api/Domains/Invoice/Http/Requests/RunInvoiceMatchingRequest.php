<?php

namespace Domains\Invoice\Http\Requests;

use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

class RunInvoiceMatchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplierInvoice = $this->route('supplierInvoice');

        return $supplierInvoice instanceof SupplierInvoice
            && ($this->user()?->can('review', $supplierInvoice) ?? false);
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
