<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\SourcingPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseSourcingIntakeReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sourcingPath' => ['required', Rule::in([SourcingPath::NoSourcingRequired->value])],
            'decisionReason' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
