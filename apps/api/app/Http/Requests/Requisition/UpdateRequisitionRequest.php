<?php

namespace App\Http\Requests\Requisition;

use App\Tenancy\CurrentTenant;
use Domains\Requisition\Models\RequisitionCostCenter;
use Domains\Requisition\Models\RequisitionDepartment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequisitionRequest extends FormRequest
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
        $tenantId = app(CurrentTenant::class)->get()->id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'lockVersion' => ['required', 'integer', 'min:0'],
            'businessJustification' => ['sometimes', 'nullable', 'string'],
            'neededByDate' => ['sometimes', 'nullable', 'date'],
            'department' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::exists(RequisitionDepartment::class, 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('active', true)),
            ],
            'projectId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'costCenter' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::exists(RequisitionCostCenter::class, 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('active', true)),
            ],
            'deliveryLocation' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'lineItems' => ['sometimes', 'array'],
            'lineItems.*.name' => ['required_with:lineItems', 'string', 'max:255'],
            'lineItems.*.description' => ['nullable', 'string'],
            'lineItems.*.quantity' => ['required_with:lineItems', 'numeric', 'gt:0'],
            'lineItems.*.unit' => ['required_with:lineItems', 'string', 'max:50'],
            'lineItems.*.estimatedUnitPrice' => ['required_with:lineItems', 'numeric', 'gte:0'],
            'lineItems.*.currency' => ['required_with:lineItems', 'string', 'size:3'],
        ];
    }
}
