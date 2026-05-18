<?php

namespace Domains\Approval\Http\Requests;

use App\Tenancy\CurrentTenant;
use Domains\Approval\Http\Requests\Concerns\ValidatesApprovalPolicyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalPolicyVersionRequest extends FormRequest
{
    use ValidatesApprovalPolicyRules;

    public function authorize(): bool
    {
        return app(CurrentTenant::class)->roleFor($this->user()) === 'admin';
    }

    public function rules(): array
    {
        return [
            'priority' => ['sometimes', 'integer', 'min:1'],
            'rules' => ['required', 'array'],
            'rules.*.field' => ['required_with:rules.*', 'string', 'max:255'],
            'rules.*.operator' => ['required_with:rules.*', 'string', Rule::in(['equals', 'in', 'gte', 'lte', 'between'])],
            'rules.*.value' => ['present'],
            'routeTemplate' => ['required', 'array'],
            'routeTemplate.stages' => ['required', 'array', 'min:1'],
            'routeTemplate.stages.*.name' => ['required', 'string', 'max:255'],
            'routeTemplate.stages.*.completionRule' => ['required', Rule::in(['all', 'any'])],
            'routeTemplate.stages.*.approvers' => ['required', 'array', 'min:1'],
            'routeTemplate.stages.*.approvers.*.type' => ['required', 'string', 'max:255'],
            'slaRules' => ['sometimes', 'array'],
            'slaRules.*.stage' => ['required_with:slaRules.*', 'string', 'max:255'],
            'slaRules.*.dueInHours' => ['required_with:slaRules.*', 'integer', 'min:1'],
            'slaRules.*.escalateAfterHours' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
