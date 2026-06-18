<?php

namespace Domains\Approval\Services;

use Domains\Approval\Data\ApprovalContextData;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApprovalPolicyMatcher
{
    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{
     *   matchedPolicy: array<string, mixed>,
     *   matchedVersion: array<string, mixed>,
     *   matchedConditions: array<int, array<string, mixed>>,
     *   routeTemplate: array<string, mixed>,
     *   slaRules: array<int, array<string, mixed>>,
     *   warnings: array<int, array<string, string>>
     * }
     */
    public function match(ApprovalContextData $context, array $candidates, bool $allowUnmatchedPolicyFallback = false): array
    {
        usort($candidates, function (array $left, array $right): int {
            $priorityComparison = ((int) ($right['matchedVersion']['priority'] ?? 100)) <=> ((int) ($left['matchedVersion']['priority'] ?? 100));
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            $versionComparison = ((int) ($right['matchedVersion']['versionNumber'] ?? 0)) <=> ((int) ($left['matchedVersion']['versionNumber'] ?? 0));
            if ($versionComparison !== 0) {
                return $versionComparison;
            }

            return strcmp((string) ($left['matchedVersion']['id'] ?? ''), (string) ($right['matchedVersion']['id'] ?? ''));
        });

        $fallback = null;
        $missingContextFields = [];
        foreach ($candidates as $candidate) {
            $rules = $candidate['rules'] ?? [];

            if ($rules === []) {
                $fallback ??= $candidate;

                continue;
            }

            $evaluation = $this->evaluateRules($context, $rules);
            if ($evaluation['matched']) {
                return [
                    'matchedPolicy' => $candidate['matchedPolicy'],
                    'matchedVersion' => $candidate['matchedVersion'],
                    'matchedConditions' => $evaluation['conditions'],
                    'routeTemplate' => $candidate['routeTemplate'],
                    'slaRules' => $candidate['slaRules'] ?? [],
                    'warnings' => [],
                ];
            }

            $missingContextFields = array_values(array_unique(array_merge(
                $missingContextFields,
                $evaluation['missingContextFields'],
            )));
        }

        if ($candidates === []) {
            abort(404, 'No approval policy versions are available.');
        }

        $selected = $fallback ?? ($allowUnmatchedPolicyFallback ? $candidates[0] : null);

        $warnings = [];
        if ($fallback !== null) {
            $warnings[] = [
                'code' => 'fallback_policy',
                'message' => 'No policy rules matched; using fallback policy version.',
            ];
            $matchedConditions = [];
        } elseif ($allowUnmatchedPolicyFallback && $selected !== null) {
            $evaluation = $this->evaluateRules($context, $selected['rules'] ?? []);
            $matchedConditions = $evaluation['conditions'];
            $warnings[] = [
                'code' => 'fallback_policy',
                'message' => 'No policy rules matched; using the highest priority policy version.',
            ];
        } else {
            throw new ConflictHttpException('No approval policy version matched the approval context.');
        }

        if ($missingContextFields !== []) {
            $warnings[] = [
                'code' => 'missing_context',
                'message' => sprintf(
                    'Missing required approval context affected policy matching: %s',
                    implode(', ', $missingContextFields),
                ),
            ];
        }

        return [
            'matchedPolicy' => $selected['matchedPolicy'],
            'matchedVersion' => $selected['matchedVersion'],
            'matchedConditions' => $matchedConditions,
            'routeTemplate' => $selected['routeTemplate'],
            'slaRules' => $selected['slaRules'] ?? [],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array{
     *   matched: bool,
     *   conditions: array<int, array<string, mixed>>,
     *   missingContextFields: array<int, string>
     * }
     */
    private function evaluateRules(ApprovalContextData $context, array $rules): array
    {
        $conditions = [];
        $matched = true;
        $missingContextFields = [];

        foreach ($rules as $rule) {
            $actualValue = $this->resolveFieldValue($context, (string) ($rule['field'] ?? ''));
            $ruleMatched = $this->evaluateRule($actualValue, (string) ($rule['operator'] ?? ''), $rule['value'] ?? null);

            $conditions[] = [
                'field' => (string) ($rule['field'] ?? ''),
                'operator' => (string) ($rule['operator'] ?? ''),
                'value' => $rule['value'] ?? null,
                'matched' => $ruleMatched,
                'summary' => sprintf(
                    '%s %s %s %s',
                    (string) ($rule['field'] ?? ''),
                    (string) ($rule['operator'] ?? ''),
                    $this->formatValue($rule['value'] ?? null),
                    $ruleMatched ? 'matched' : 'did not match',
                ),
            ];

            if (! $ruleMatched) {
                $matched = false;
            }

            if ($actualValue === null && in_array((string) ($rule['field'] ?? ''), ['riskClassification', 'vendorId', 'matchingStatus', 'exceptionCount', 'hasValueAdjustments'], true)) {
                $missingContextFields[] = (string) $rule['field'];
            }
        }

        return [
            'matched' => $matched,
            'conditions' => $conditions,
            'missingContextFields' => array_values(array_unique($missingContextFields)),
        ];
    }

    private function evaluateRule(mixed $actualValue, string $operator, mixed $expectedValue): bool
    {
        return match ($operator) {
            'equals' => $this->equals($actualValue, $expectedValue),
            'in' => $this->inSet($actualValue, $expectedValue),
            'gte' => is_numeric($actualValue) && is_numeric($expectedValue) && (float) $actualValue >= (float) $expectedValue,
            'lte' => is_numeric($actualValue) && is_numeric($expectedValue) && (float) $actualValue <= (float) $expectedValue,
            'between' => is_numeric($actualValue)
                && is_array($expectedValue)
                && count($expectedValue) === 2
                && is_numeric($expectedValue[0])
                && is_numeric($expectedValue[1])
                && (float) $actualValue >= (float) $expectedValue[0]
                && (float) $actualValue <= (float) $expectedValue[1],
            default => false,
        };
    }

    private function equals(mixed $actualValue, mixed $expectedValue): bool
    {
        if (is_array($actualValue) && is_array($expectedValue)) {
            return $this->normalizeStringSet($actualValue) === $this->normalizeStringSet($expectedValue);
        }

        if (is_array($actualValue)) {
            return in_array((string) $expectedValue, $this->normalizeStringSet($actualValue), true);
        }

        if (is_array($expectedValue)) {
            return in_array((string) $actualValue, $this->normalizeStringSet($expectedValue), true);
        }

        return $actualValue === $expectedValue || (string) $actualValue === (string) $expectedValue;
    }

    private function inSet(mixed $actualValue, mixed $expectedValue): bool
    {
        $expectedValues = is_array($expectedValue) ? $expectedValue : [$expectedValue];

        if (is_array($actualValue)) {
            return count(array_intersect($actualValue, $expectedValues)) > 0;
        }

        return in_array($actualValue, $expectedValues, true) || in_array((string) $actualValue, array_map('strval', $expectedValues), true);
    }

    private function resolveFieldValue(ApprovalContextData $context, string $field): mixed
    {
        return match ($field) {
            'tenantId' => $context->tenantId,
            'subjectType' => $context->subjectType,
            'requisitionId' => $context->requisitionId,
            'requesterId' => $context->requesterId,
            'amount' => $context->amount,
            'currency' => $context->currency,
            'department' => $context->department,
            'costCenter' => $context->costCenter,
            'projectId' => $context->projectId,
            'lineItemCategories' => $context->lineItemCategories,
            'riskClassification' => $context->riskClassification,
            'vendorId' => $context->vendorId,
            'awardRecommendationId' => $context->awardRecommendationId,
            'rfqId' => $context->rfqId,
            'rfqNumber' => $context->rfqNumber,
            'recommendedVendorId' => $context->recommendedVendorId,
            'recommendedVendorName' => $context->recommendedVendorName,
            'recommendedQuotationId' => $context->recommendedQuotationId,
            'recommendedQuotationVersionId' => $context->recommendedQuotationVersionId,
            'recommendedAmount' => $context->recommendedAmount,
            'recommendedCurrency' => $context->recommendedCurrency,
            'scorecardId' => $context->scorecardId,
            'scorecardWeightedTotal' => $context->scorecardWeightedTotal,
            'riskSummaryPresent' => $context->riskSummaryPresent,
            'exceptionSummaryPresent' => $context->exceptionSummaryPresent,
            'supplierInvoiceId' => $context->supplierInvoiceId,
            'purchaseOrderId' => $context->purchaseOrderId,
            'purchaseOrderNumber' => $context->purchaseOrderNumber,
            'matchingStatus' => $context->matchingStatus,
            'exceptionCount' => $context->exceptionCount,
            'hasValueAdjustments' => $context->hasValueAdjustments,
            'originalInvoiceAmount' => $context->originalInvoiceAmount,
            'totalVarianceAdjusted' => $context->totalVarianceAdjusted,
            default => null,
        };
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeStringSet(array $values): array
    {
        $normalized = array_map(static fn (mixed $value): string => trim((string) $value), $values);
        sort($normalized);

        return array_values($normalized);
    }
}
