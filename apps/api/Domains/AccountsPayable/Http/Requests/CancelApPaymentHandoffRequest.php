<?php

namespace Domains\AccountsPayable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelApPaymentHandoffRequest extends FormRequest
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
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
