<?php

namespace Domains\Approval\Http\Requests;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        $user = $this->user();

        if ($tenant === null || $user === null) {
            return false;
        }

        $role = $tenant->roleFor($user);

        return in_array($role, ['admin', 'approver'], true);
    }

    public function rules(): array
    {
        $tenant = app(CurrentTenant::class)->get();

        return [
            'delegateId' => [
                'required',
                'integer',
                Rule::exists('tenant_user', 'user_id')->where(fn ($query) => $query->where('tenant_id', $tenant?->id)),
            ],
            'scope' => ['required', 'string', Rule::in(['task_specific', 'category', 'department', 'cost_center', 'project', 'all'])],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
