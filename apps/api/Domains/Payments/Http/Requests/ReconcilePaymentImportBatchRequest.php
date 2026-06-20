<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconcilePaymentImportBatchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersions' => ['nullable', 'array'],
            'lockVersions.*' => ['integer', 'min:1'],
        ];
    }
}
