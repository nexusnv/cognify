<?php

namespace App\Observability\SystemStatus;

class SystemStatusCheckResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $remediation = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array{id: string, label: string, status: string, message: string, remediation: ?string, metadata: object}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status,
            'message' => $this->message,
            'remediation' => $this->remediation,
            'metadata' => (object) $this->metadata,
        ];
    }
}
