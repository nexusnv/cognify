<?php

namespace Domains\Requisition\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestRequisitionChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $requestedFields = $this->input('requestedFields');

        if (is_array($requestedFields)) {
            $this->merge([
                'requestedFields' => array_values(array_unique($requestedFields)),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'requestedFields' => ['sometimes', 'array', 'max:50'],
            'requestedFields.*' => ['string', 'max:80', 'distinct'],
        ];
    }
}
