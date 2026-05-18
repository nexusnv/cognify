<?php

namespace Domains\Approval\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Domains\Approval\Models\ApprovalDelegation
 */
class ApprovalDelegationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'delegatorId' => (string) $this->delegator_id,
            'delegateId' => (string) $this->delegate_id,
            'scope' => $this->scope,
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'status' => $this->status->value,
            'reason' => $this->reason,
            'delegator' => $this->userSummary($this->whenLoaded('delegator')),
            'delegate' => $this->userSummary($this->whenLoaded('delegate')),
            'createdById' => $this->created_by !== null ? (string) $this->created_by : null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function userSummary(mixed $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
