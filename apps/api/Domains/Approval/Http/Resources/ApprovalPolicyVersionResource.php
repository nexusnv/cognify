<?php

namespace Domains\Approval\Http\Resources;

use Domains\Approval\Models\ApprovalPolicyVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalPolicyVersion
 */
class ApprovalPolicyVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'policyId' => (string) $this->approval_policy_id,
            'versionNumber' => $this->version_number,
            'status' => $this->status->value,
            'effectiveFrom' => $this->effective_from?->toISOString(),
            'effectiveUntil' => $this->effective_until?->toISOString(),
            'priority' => $this->priority,
            'rules' => $this->rules ?? [],
            'routeTemplate' => $this->route_template ?? ['stages' => []],
            'slaRules' => $this->sla_rules ?? [],
            'publishedById' => $this->published_by !== null ? (string) $this->published_by : null,
            'publishedAt' => $this->published_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
