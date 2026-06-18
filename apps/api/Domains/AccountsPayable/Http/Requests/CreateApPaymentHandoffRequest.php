<?php

namespace Domains\AccountsPayable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateApPaymentHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization deferred to controller (findTenantApPaymentHandoff + policy check)
        // to avoid leaking cross-tenant handoff existence via 403 vs 404
        return true;
    }

    public function rules(): array
    {
        return [
            'invoiceIds' => ['required', 'array', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'effectivePaymentDate' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
