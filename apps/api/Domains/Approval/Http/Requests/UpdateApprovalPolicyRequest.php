<?php

namespace Domains\Approval\Http\Requests;

use App\Tenancy\CurrentTenant;
use Domains\Approval\Http\Requests\Concerns\ValidatesApprovalPolicyRules;
use Domains\Approval\Models\ApprovalPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalPolicyRequest extends FormRequest
{
    use ValidatesApprovalPolicyRules;

    public function authorize(): bool
    {
        return app(CurrentTenant::class)->roleFor($this->user()) === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $subjectType = $this->routePolicySubjectType();

        if ($subjectType !== null) {
            $this->merge(['subjectType' => $subjectType]);
        }
    }

    public function rules(): array
    {
        $subjectType = $this->routePolicySubjectType();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subjectType' => ['sometimes', Rule::in($subjectType !== null ? [$subjectType] : ['requisition', 'rfq_award_recommendation'])],
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
            'routeTemplate.stages.*.fallbackApprovers' => ['sometimes', 'array'],
            'routeTemplate.stages.*.fallbackApprovers.*.type' => ['required_with:routeTemplate.stages.*.fallbackApprovers.*', 'string', 'max:255'],
            'slaRules' => ['sometimes', 'array'],
            'slaRules.*.stage' => ['required_with:slaRules.*', 'string', 'max:255'],
            'slaRules.*.dueInHours' => ['required_with:slaRules.*', 'integer', 'min:1'],
            'slaRules.*.escalateAfterHours' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    private function routePolicySubjectType(): ?string
    {
        $policyId = $this->route('approvalPolicy');

        if ($policyId === null) {
            return null;
        }

        $tenant = app(CurrentTenant::class)->get();

        if ($tenant === null) {
            return null;
        }

        return ApprovalPolicy::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($policyId)
            ->value('subject_type');
    }
}
