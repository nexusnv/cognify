<?php

namespace Domains\Approval\Http\Requests;

use App\Tenancy\CurrentTenant;
use Domains\Approval\Http\Requests\Concerns\ValidatesApprovalPolicyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewApprovalPolicyRequest extends FormRequest
{
    use ValidatesApprovalPolicyRules;

    public function authorize(): bool
    {
        return app(CurrentTenant::class)->roleFor($this->user()) === 'admin';
    }

    public function rules(): array
    {
        return [
            'policyName' => ['sometimes', 'string', 'max:255'],
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
            'routeTemplate.stages.*.fallbackApprovers' => ['sometimes', 'array'],
            'routeTemplate.stages.*.fallbackApprovers.*.type' => ['required_with:routeTemplate.stages.*.fallbackApprovers.*', 'string', 'max:255'],
            'slaRules' => ['sometimes', 'array'],
            'slaRules.*.stage' => ['required_with:slaRules.*', 'string', 'max:255'],
            'slaRules.*.dueInHours' => ['required_with:slaRules.*', 'integer', 'min:1'],
            'slaRules.*.escalateAfterHours' => ['sometimes', 'integer', 'min:1'],
            'context' => ['sometimes', 'array'],
            'context.subjectType' => ['sometimes', Rule::in(['requisition', 'rfq_award_recommendation'])],
            'context.requisitionId' => ['sometimes', 'string', 'max:255'],
            'context.requesterId' => ['sometimes', 'string', 'max:255'],
            'context.amount' => ['sometimes', 'numeric'],
            'context.currency' => ['sometimes', 'string', 'size:3'],
            'context.department' => ['sometimes', 'string', 'max:255'],
            'context.costCenter' => ['sometimes', 'string', 'max:255'],
            'context.projectId' => ['sometimes', 'string', 'max:255'],
            'context.lineItemCategories' => ['sometimes', 'array'],
            'context.lineItemCategories.*' => ['string', 'max:255'],
            'context.riskClassification' => ['sometimes', 'string', 'max:255'],
            'context.vendorId' => ['sometimes', 'string', 'max:255'],
            'context.awardRecommendationId' => ['sometimes', 'string', 'max:255'],
            'context.rfqId' => ['sometimes', 'string', 'max:255'],
            'context.rfqNumber' => ['sometimes', 'string', 'max:255'],
            'context.recommendedVendorId' => ['sometimes', 'string', 'max:255'],
            'context.recommendedVendorName' => ['sometimes', 'string', 'max:255'],
            'context.recommendedQuotationId' => ['sometimes', 'string', 'max:255'],
            'context.recommendedQuotationVersionId' => ['sometimes', 'string', 'max:255'],
            'context.recommendedAmount' => ['sometimes', 'numeric'],
            'context.recommendedCurrency' => ['sometimes', 'string', 'size:3'],
            'context.scorecardId' => ['sometimes', 'string', 'max:255'],
            'context.scorecardWeightedTotal' => ['sometimes', 'numeric'],
            'context.riskSummaryPresent' => ['sometimes', 'boolean'],
            'context.exceptionSummaryPresent' => ['sometimes', 'boolean'],
        ];
    }
}
