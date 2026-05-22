<?php

namespace Domains\Quotation\Http\Requests;

use App\Auth\TenantRole;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class ApproveQuotationNormalizationRequest extends FormRequest
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
        $requiresApprovalNote = $this->route()?->getActionMethod() === 'approveWithWarnings';

        return [
            'approvalNote' => $requiresApprovalNote
                ? ['required', 'string', 'max:5000']
                : ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
