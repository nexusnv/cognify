<?php

namespace Domains\Invoice\Http\Requests;

use Domains\Invoice\Data\SupplierInvoiceReviewChecklistData;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkSupplierInvoiceNeedsInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplierInvoice = $this->route('supplierInvoice');

        return $supplierInvoice instanceof SupplierInvoice
            && ($this->user()?->can('review', $supplierInvoice) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'notes' => ['required', 'string', 'max:2000'],
            'checklist' => ['required', 'array'],
            'checklist.*.status' => ['required', 'string', Rule::in(SupplierInvoiceReviewChecklistData::STATUSES)],
            'checklist.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
