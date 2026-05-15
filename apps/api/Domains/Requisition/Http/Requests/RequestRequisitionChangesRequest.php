<?php

namespace Domains\Requisition\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestRequisitionChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'requestedFields' => ['sometimes', 'array'],
            'requestedFields.*' => ['string', 'max:80'],
        ];
    }
}
