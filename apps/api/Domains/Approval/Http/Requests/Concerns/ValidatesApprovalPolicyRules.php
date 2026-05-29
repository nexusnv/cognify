<?php

namespace Domains\Approval\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

trait ValidatesApprovalPolicyRules
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateBetweenRuleBounds($validator);
            $this->validateRuleFieldsForSubject($validator);
        });
    }

    private function validateBetweenRuleBounds(Validator $validator): void
    {
        foreach ((array) $this->input('rules', []) as $index => $rule) {
            if (($rule['operator'] ?? null) !== 'between') {
                continue;
            }

            $value = $rule['value'] ?? null;

            if (! is_array($value) || count($value) !== 2) {
                $validator->errors()->add(
                    "rules.{$index}.value",
                    'Between bounds must contain exactly two numeric values in ascending order.',
                );

                continue;
            }

            if (! is_numeric($value[0]) || ! is_numeric($value[1]) || (float) $value[0] > (float) $value[1]) {
                $validator->errors()->add(
                    "rules.{$index}.value",
                    'Between bounds must contain exactly two numeric values in ascending order.',
                );
            }
        }
    }

    private function validateRuleFieldsForSubject(Validator $validator): void
    {
        $subjectType = $this->input('subjectType', $this->input('context.subjectType', 'requisition'));
        $supportedFields = match ($subjectType) {
            'rfq_award_recommendation' => [
                'recommendedAmount',
                'recommendedCurrency',
                'recommendedVendorId',
                'scorecardWeightedTotal',
                'riskClassification',
                'riskSummaryPresent',
                'exceptionSummaryPresent',
            ],
            default => [
                'amount',
                'department',
                'costCenter',
                'projectId',
                'riskClassification',
            ],
        };

        foreach ((array) $this->input('rules', []) as $index => $rule) {
            $field = $rule['field'] ?? null;

            if (! is_string($field) || in_array($field, $supportedFields, true)) {
                continue;
            }

            $validator->errors()->add(
                "rules.{$index}.field",
                "{$field} is not available for {$subjectType} policies.",
            );
        }
    }
}
