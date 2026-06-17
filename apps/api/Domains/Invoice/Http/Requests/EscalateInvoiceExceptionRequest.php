<?php

namespace Domains\Invoice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EscalateInvoiceExceptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'escalatedToUserId' => ['required', 'string', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
