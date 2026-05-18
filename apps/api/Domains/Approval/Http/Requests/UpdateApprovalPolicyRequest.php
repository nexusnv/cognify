<?php

namespace Domains\Approval\Http\Requests;

use App\Tenancy\CurrentTenant;
use Domains\Approval\Http\Requests\Concerns\ValidatesApprovalPolicyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalPolicyRequest extends FormRequest
{
    use ValidatesApprovalPolicyRules;

    public function authorize(): bool
    {
        return app(CurrentTenant::class)->roleFor($this->user()) === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subjectType' => ['sometimes', Rule::in(['requisition'])],
            'priority' => ['sometimes', 'integer', 'min:1'],
            'rules' => ['sometimes', 'array'],
            'rules.*.field' => ['required_with:rules.*', 'string', 'max:255'],
            'rules.*.operator' => ['required_with:rules.*', 'string', Rule::in(['equals', 'in', 'gte', 'lte', 'between'])],
            'rules.*.value' => ['present'],
            'routeTemplate' => ['sometimes', 'array'],
            'routeTemplate.stages' => ['required_with:routeTemplate', 'array', 'min:1'],
            'routeTemplate.stages.*.name' => ['required_with:routeTemplate.stages.*', 'string', 'max:255'],
            'routeTemplate.stages.*.completionRule' => ['required_with:routeTemplate.stages.*', Rule::in(['all', 'any'])],
            'routeTemplate.stages.*.approvers' => ['required_with:routeTemplate.stages.*', 'array', 'min:1'],
            'routeTemplate.stages.*.approvers.*.type' => ['required_with:routeTemplate.stages.*.approvers.*', 'string', 'max:255'],
            'slaRules' => ['sometimes', 'array'],
            'slaRules.*.stage' => ['required_with:slaRules.*', 'string', 'max:255'],
            'slaRules.*.dueInHours' => ['required_with:slaRules.*', 'integer', 'min:1'],
            'slaRules.*.escalateAfterHours' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
