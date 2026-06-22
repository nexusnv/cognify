<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseApPaymentHandoffWithVarianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'varianceReason' => ['required', 'string', 'min:5', 'max:2000'],
            'remittanceReference' => ['nullable', 'string', 'max:255'],
            'remittanceAdviceSentAt' => ['nullable', 'date'],
        ];
    }
}
