<?php

namespace Domains\Vendor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListVendorsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
