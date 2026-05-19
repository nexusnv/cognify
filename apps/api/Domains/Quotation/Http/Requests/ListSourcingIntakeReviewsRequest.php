<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\SourcingIntakeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSourcingIntakeReviewsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preset' => ['sometimes', 'string', Rule::in(['unassigned', 'mine', 'needs_clarification', 'ready_for_rfq', 'closed'])],
            'status' => ['sometimes', 'string', Rule::enum(SourcingIntakeStatus::class)],
            'assignedBuyer' => ['sometimes', 'string'],
            'department' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(['updated_desc', 'target_date_asc', 'needed_by_asc', 'amount_desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
