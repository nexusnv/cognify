<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkApPaymentHandoffPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'remittanceReference' => ['nullable', 'string', 'max:255'],
            'remittanceAdviceSentAt' => ['nullable', 'date'],
        ];
    }
}
