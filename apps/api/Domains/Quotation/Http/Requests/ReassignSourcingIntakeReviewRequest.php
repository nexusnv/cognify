<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReassignSourcingIntakeReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'buyerId' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
