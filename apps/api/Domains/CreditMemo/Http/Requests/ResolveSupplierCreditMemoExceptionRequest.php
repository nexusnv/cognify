<?php

namespace Domains\CreditMemo\Http\Requests;

use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveSupplierCreditMemoExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'resolutionType' => ['required', Rule::enum(SupplierCreditMemoExceptionResolutionType::class)],
            'resolutionNotes' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
