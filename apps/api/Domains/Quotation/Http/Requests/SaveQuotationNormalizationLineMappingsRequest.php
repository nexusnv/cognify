<?php

namespace Domains\Quotation\Http\Requests;

use App\Auth\TenantRole;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Domains\Quotation\States\QuotationNormalizationPricingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuotationNormalizationLineMappingsRequest extends FormRequest
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
            'lineGroups' => ['required', 'array', 'min:1'],
            'lineGroups.*.groupNumber' => ['required', 'integer', 'min:1'],
            'lineGroups.*.pricingMode' => ['required', 'string', Rule::enum(QuotationNormalizationPricingMode::class)],
            'lineGroups.*.description' => ['required', 'string', 'max:5000'],
            'lineGroups.*.currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'lineGroups.*.bundleTotalAmount' => ['sometimes', 'nullable', 'numeric'],
            'lineGroups.*.notes' => ['sometimes', 'nullable', 'string'],
            'lineGroups.*.mappings' => ['required', 'array', 'min:1'],
            'lineGroups.*.mappings.*.rfqLineItemId' => ['sometimes', 'nullable', 'string'],
            'lineGroups.*.mappings.*.quotationVersionLineItemId' => ['sometimes', 'nullable', 'string'],
            'lineGroups.*.mappings.*.mappingType' => ['required', 'string', Rule::enum(QuotationNormalizationMappingType::class)],
            'lineGroups.*.mappings.*.quantity' => ['sometimes', 'nullable', 'numeric'],
            'lineGroups.*.mappings.*.unit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lineGroups.*.mappings.*.unitPrice' => ['sometimes', 'nullable', 'numeric'],
            'lineGroups.*.mappings.*.lineTotal' => ['sometimes', 'nullable', 'numeric'],
            'lineGroups.*.mappings.*.buyerNote' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
