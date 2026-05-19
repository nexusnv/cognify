<?php

namespace Domains\Approval\Http\Resources;

use Domains\Approval\Models\ApprovalPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalPolicy
 */
class ApprovalPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'subjectType' => $this->subject_type,
            'status' => $this->status->value,
            'versions' => ApprovalPolicyVersionResource::collection($this->whenLoaded('versions')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
