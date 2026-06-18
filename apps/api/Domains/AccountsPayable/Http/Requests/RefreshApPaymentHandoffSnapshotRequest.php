<?php

namespace Domains\AccountsPayable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshApPaymentHandoffSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization deferred to controller (findTenantHandoff + policy check)
        // to avoid leaking cross-tenant handoff existence via 403 vs 404
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
