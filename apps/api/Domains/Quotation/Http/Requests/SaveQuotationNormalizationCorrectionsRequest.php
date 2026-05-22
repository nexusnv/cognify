<?php

namespace Domains\Quotation\Http\Requests;

use App\Auth\TenantRole;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class SaveQuotationNormalizationCorrectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && in_array($tenant->roleFor($this->user()), [
            TenantRole::Buyer->value,
            TenantRole::Admin->value,
        ], true);
    }

    public function rules(): array
    {
        return [
            'corrections' => ['required', 'array', 'min:1'],
            'corrections.*.fieldPath' => ['required', 'string', 'max:255'],
            'corrections.*.correctedValue' => ['required'],
            'corrections.*.issueId' => ['sometimes', 'nullable', 'string'],
            'corrections.*.correctionNote' => ['required', 'string', 'max:5000'],
            'corrections.*.resolutionNote' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
