<?php

namespace Domains\Approval\Http\Requests;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DelegateApprovalTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $tenant = app(CurrentTenant::class)->get();

        return [
            'approvalDelegationId' => [
                'required',
                'integer',
                Rule::exists('approval_delegations', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant?->id)),
            ],
            'lockVersion' => ['required', 'integer'],
        ];
    }
}
