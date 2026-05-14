<?php

namespace App\Observability\SystemStatus;

class SystemStatusCheckResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $status,
        public readonly array $details = [],
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @return array{status: string, message: ?string, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}

