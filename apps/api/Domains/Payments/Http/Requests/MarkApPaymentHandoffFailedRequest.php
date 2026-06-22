<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkApPaymentHandoffFailedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'failureCode' => ['required', 'string', 'in:bank_rejected,insufficient_funds,vendor_blocked,system_error,other'],
            'failureReason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
