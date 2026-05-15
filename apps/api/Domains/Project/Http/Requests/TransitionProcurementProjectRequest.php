<?php

namespace Domains\Project\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionProcurementProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
