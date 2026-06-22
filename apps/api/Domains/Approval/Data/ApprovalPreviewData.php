<?php

namespace Domains\Approval\Data;

final class ApprovalPreviewData
{
    /**
     * @param  array<string, mixed>  $matchedPolicy
     * @param  array<string, mixed>  $matchedVersion
     * @param  array<int, array<string, mixed>>  $matchedConditions
     * @param  array<int, array<string, mixed>>  $stages
     * @param  array<int, array<string, string>>  $warnings
     */
    public function __construct(
        public readonly array $matchedPolicy,
        public readonly array $matchedVersion,
        public readonly array $matchedConditions,
        public readonly array $stages,
        public readonly array $warnings,
        public readonly ?string $estimatedDueAt,
        public readonly bool $createsTasks = false,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'matchedPolicy' => $this->matchedPolicy,
            'matchedVersion' => $this->matchedVersion,
            'matchedConditions' => $this->matchedConditions,
            'stages' => $this->stages,
            'warnings' => $this->warnings,
            'estimatedDueAt' => $this->estimatedDueAt,
            'createsTasks' => $this->createsTasks,
            'context' => $this->context,
        ];
    }
}
