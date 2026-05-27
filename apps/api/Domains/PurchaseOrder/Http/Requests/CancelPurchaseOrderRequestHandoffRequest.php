<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelPurchaseOrderRequestHandoffRequest extends FormRequest
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
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
