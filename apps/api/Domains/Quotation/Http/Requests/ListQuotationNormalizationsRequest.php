<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQuotationNormalizationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'array', 'min:1'],
            'status.*' => ['string', Rule::in([
                'pending',
                'processing',
                'needs_review',
                'ready_for_approval',
                'approved',
                'approved_with_warnings',
                'failed',
                'superseded',
            ])],
        ];
    }
}
