<?php

namespace Domains\Approval\Services;

use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Data\ApprovalRouteStageData;

class ApprovalRouteBuilder
{
    /**
     * @param array<string, mixed> $matchedVersion
     * @param array<int, array<string, mixed>> $matchedConditions
     * @param array<int, array<string, string>> $warnings
     * @return array{
     *   stages: array<int, array<string, mixed>>,
     *   warnings: array<int, array<string, string>>,
     *   estimatedDueAt: ?string
     * }
     */
    public function build(ApprovalContextData $context, array $matchedVersion, array $matchedConditions, array $warnings = []): array
    {
        $routeTemplate = $matchedVersion['routeTemplate'] ?? ['stages' => []];
        $slaRules = $matchedVersion['slaRules'] ?? [];
        $stages = [];
        $estimatedDueAt = null;
        $routeStart = now();

        foreach (($routeTemplate['stages'] ?? []) as $index => $stage) {
            $slaRule = $this->findSlaRule($slaRules, (string) ($stage['name'] ?? ''));
            $dueAt = null;
            $stageWarnings = [];

            if ($slaRule !== null) {
                $dueAt = $routeStart->copy()->addHours((int) $slaRule['dueInHours'])->toISOString();
                $estimatedDueAt = $dueAt;
            } else {
                $stageWarnings[] = [
                    'code' => 'missing_sla',
                    'message' => sprintf('No SLA due date configured for stage %s.', (string) ($stage['name'] ?? '')),
                ];
            }

            $approvers = $this->normalizeApprovers($stage['approvers'] ?? []);
            $fallbackApprovers = $this->normalizeApprovers($stage['fallbackApprovers'] ?? []);

            if ($fallbackApprovers === []) {
                $stageWarnings[] = [
                    'code' => 'missing_fallback_approver',
                    'message' => sprintf('No fallback approver configured for stage %s.', (string) ($stage['name'] ?? '')),
                ];
            }

            $stageData = new ApprovalRouteStageData(
                name: (string) ($stage['name'] ?? sprintf('Stage %d', $index + 1)),
                completionRule: (string) ($stage['completionRule'] ?? 'all'),
                approvers: $approvers,
                fallbackApprovers: $fallbackApprovers,
                dueAt: $dueAt,
                warnings: $stageWarnings,
            );

            $stages[] = $stageData->toArray();
        }

        if ($estimatedDueAt === null && $stages !== []) {
            $estimatedDueAt = $stages[array_key_last($stages)]['dueAt'];
        }

        return [
            'stages' => $stages,
            'warnings' => array_values(array_merge($warnings, $this->contextWarnings($context, $matchedConditions))),
            'estimatedDueAt' => $estimatedDueAt,
        ];
    }

    /**
     * @param array<int, mixed> $approvers
     * @return array<int, array<string, mixed>>
     */
    private function normalizeApprovers(array $approvers): array
    {
        return array_values(array_map(function (mixed $approver): array {
            return [
                'type' => (string) ($approver['type'] ?? 'role'),
                'role' => isset($approver['role']) ? (string) $approver['role'] : null,
                'userId' => isset($approver['userId']) ? (string) $approver['userId'] : null,
                'label' => isset($approver['label']) && $approver['label'] !== ''
                    ? (string) $approver['label']
                    : ($approver['role'] ?? $approver['userId'] ?? $approver['type'] ?? 'Approver'),
            ];
        }, $approvers));
    }

    /**
     * @param array<int, array<string, mixed>> $slaRules
     * @return array<string, mixed>|null
     */
    private function findSlaRule(array $slaRules, string $stageName): ?array
    {
        foreach ($slaRules as $slaRule) {
            if ((string) ($slaRule['stage'] ?? '') === $stageName) {
                return $slaRule;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $matchedConditions
     * @return array<int, array<string, string>>
     */
    private function contextWarnings(ApprovalContextData $context, array $matchedConditions): array
    {
        $missing = $context->missingRequiredContext($this->requiredContextFields($matchedConditions));

        if ($missing === []) {
            return [];
        }

        return [[
            'code' => 'missing_context',
            'message' => sprintf('Missing required approval context: %s', implode(', ', $missing)),
        ]];
    }

    /**
     * @param array<int, array<string, mixed>> $matchedConditions
     * @return array<int, string>
     */
    private function requiredContextFields(array $matchedConditions): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (array $condition): ?string => in_array(
                $condition['field'] ?? null,
                ['riskClassification', 'vendorId'],
                true,
            ) ? (string) $condition['field'] : null,
            $matchedConditions,
        ))));
    }
}
