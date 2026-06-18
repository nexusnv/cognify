<?php

namespace Domains\AccountsPayable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryPaymentInductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization delegated to policy
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
