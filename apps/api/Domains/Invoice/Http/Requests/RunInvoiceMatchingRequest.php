<?php

namespace Domains\Invoice\Http\Requests;

use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

class RunInvoiceMatchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization deferred to controller (findTenantSupplierInvoice + policy check)
        // to avoid leaking cross-tenant invoice existence via 403 vs 404
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
