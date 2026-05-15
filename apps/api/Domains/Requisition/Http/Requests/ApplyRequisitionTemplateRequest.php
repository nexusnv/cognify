<?php

namespace Domains\Requisition\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequisitionTemplateRequest extends FormRequest
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
            'templateId' => ['required', 'string'],
            'mode' => ['required', 'string', 'in:fill-empty,replace'],
            'lockVersion' => ['required', 'integer', 'min:0'],
        ];
    }
}
