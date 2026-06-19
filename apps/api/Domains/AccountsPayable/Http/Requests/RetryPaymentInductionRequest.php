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
            // Optimistic locking is not enforced by the retry action; the
            // field is accepted for API consistency but not validated.
        ];
    }
}
