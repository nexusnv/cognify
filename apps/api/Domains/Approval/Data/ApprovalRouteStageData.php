<?php

namespace Domains\Approval\Data;

final class ApprovalRouteStageData
{
    /**
     * @param  array<int, array<string, mixed>>  $approvers
     * @param  array<int, array<string, mixed>>  $fallbackApprovers
     * @param  array<int, array<string, string>>  $warnings
     */
    public function __construct(
        public readonly string $name,
        public readonly string $completionRule,
        public readonly array $approvers,
        public readonly array $fallbackApprovers,
        public readonly ?string $dueAt,
        public readonly array $warnings = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'completionRule' => $this->completionRule,
            'approvers' => $this->approvers,
            'fallbackApprovers' => $this->fallbackApprovers,
            'dueAt' => $this->dueAt,
            'warnings' => $this->warnings,
        ];
    }
}
