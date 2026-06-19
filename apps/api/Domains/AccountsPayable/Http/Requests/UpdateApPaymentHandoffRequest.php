<?php

namespace Domains\AccountsPayable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApPaymentHandoffRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'effectivePaymentDate' => ['nullable', 'date'],
            'remittanceReference' => ['nullable', 'string', 'max:255'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
