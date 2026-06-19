<?php

namespace Domains\AccountsPayable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceInvoiceOnPaymentHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization delegated to policy
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
