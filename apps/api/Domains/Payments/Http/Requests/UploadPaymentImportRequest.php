<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentImportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
