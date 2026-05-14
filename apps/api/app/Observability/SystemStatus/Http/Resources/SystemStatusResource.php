<?php

namespace App\Observability\SystemStatus\Http\Resources;

use App\Observability\SystemStatus\SystemStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SystemStatus
 */
class SystemStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'environment' => $this->environment,
            'service' => $this->service,
            'version' => $this->version,
            'checkedAt' => $this->checkedAt->toISOString(),
            'checks' => $this->checks,
            'demo' => $this->demo,
        ];
    }
}

