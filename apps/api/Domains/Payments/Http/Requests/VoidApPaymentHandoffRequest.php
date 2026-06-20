<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidApPaymentHandoffRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'voidReason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
