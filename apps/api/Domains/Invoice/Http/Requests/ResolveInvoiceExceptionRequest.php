<?php

namespace Domains\Invoice\Http\Requests;

use Domains\Invoice\Data\SupplierInvoiceExceptionResolutionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveInvoiceExceptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'resolutionType' => ['required', 'string', Rule::in(SupplierInvoiceExceptionResolutionData::RESOLUTION_TYPES)],
            'adjustedValue' => ['nullable', 'numeric', 'min:0', 'required_if:resolutionType,value_adjustment'],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
