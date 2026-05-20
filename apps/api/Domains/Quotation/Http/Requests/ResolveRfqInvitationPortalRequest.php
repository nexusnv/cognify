<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveRfqInvitationPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:32', 'max:128'],
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'token' => (string) $this->route('token'),
        ]);
    }
}
