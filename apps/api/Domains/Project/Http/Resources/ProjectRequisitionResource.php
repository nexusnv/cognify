<?php

namespace Domains\Project\Http\Resources;

use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use RuntimeException;

/**
 * @mixin Requisition
 */
class ProjectRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requester = $this->whenLoaded('requester');
        $lineItems = $this->whenLoaded('lineItems');

        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $this->status->value,
            'projectId' => $this->project_id !== null ? (string) $this->project_id : null,
            'requester' => ! $requester instanceof MissingValue && $requester !== null ? [
                'id' => (string) $requester->id,
                'name' => $requester->name,
                'email' => $requester->email,
            ] : null,
            'estimatedTotal' => $this->estimatedTotal($lineItems),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function estimatedTotal(mixed $lineItems): float
    {
        if ($lineItems instanceof MissingValue) {
            throw new RuntimeException('ProjectRequisitionResource requires the lineItems relation to compute estimatedTotal.');
        }

        return $lineItems->sum(fn ($lineItem) => (float) $lineItem->quantity * (float) $lineItem->estimated_unit_price);
    }
}
