<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRfqInvitationsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vendorIds' => ['required', 'array', 'min:1', 'max:25'],
            'vendorIds.*' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:5000'],
            'responseDueAt' => ['nullable', 'date'],
            'contactOverrides' => ['nullable', 'array'],
            'contactOverrides.*.vendorId' => ['required_with:contactOverrides', 'integer'],
            'contactOverrides.*.contactName' => ['nullable', 'string', 'max:255'],
            'contactOverrides.*.contactEmail' => ['nullable', 'email', 'max:255'],
        ];
    }
}
