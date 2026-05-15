<?php

namespace Domains\Project\Http\Resources;

use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Requisition
 */
class ProjectRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $this->status->value,
            'projectId' => $this->project_id !== null ? (string) $this->project_id : null,
            'requester' => $this->requester ? [
                'id' => (string) $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ] : null,
            'estimatedTotal' => $this->lineItems->sum(fn ($lineItem) => (float) $lineItem->quantity * (float) $lineItem->estimated_unit_price),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
