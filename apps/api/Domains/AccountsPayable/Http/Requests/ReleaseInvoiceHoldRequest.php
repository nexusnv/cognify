<?php

namespace Domains\AccountsPayable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseInvoiceHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization delegated to policy
    }

    public function rules(): array
    {
        return [
            'releaseNote' => ['required', 'string', 'min:5', 'max:2000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
