<?php

namespace Domains\Requisition\Http\Resources;

use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Domains\Requisition\Models\Requisition
 */
class RequisitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lineItems = $this->whenLoaded('lineItems');

        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'tenantId' => (string) $this->tenant_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'lockVersion' => $this->lock_version,
            'businessJustification' => $this->business_justification,
            'neededByDate' => $this->needed_by_date?->toDateString(),
            'department' => $this->department,
            'projectId' => $this->project_id,
            'costCenter' => $this->cost_center,
            'deliveryLocation' => $this->delivery_location,
            'currency' => $this->currency,
            'requester' => [
                'id' => (string) $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ],
            'lineItems' => RequisitionLineItemResource::collection($lineItems),
            'estimatedTotal' => $this->estimatedTotal(),
            'permissions' => [
                'canUpdate' => $this->status === RequisitionStatus::Draft
                    && ($request->user()?->can('update', $this->resource) ?? false),
                'canSubmit' => $this->status === RequisitionStatus::Draft
                    && ($request->user()?->can('submit', $this->resource) ?? false),
                'canViewActivity' => $request->user()?->can('view', $this->resource) ?? false,
            ],
            'submittedAt' => $this->submitted_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function estimatedTotal(): float
    {
        if (! $this->relationLoaded('lineItems') || $this->lineItems->isEmpty()) {
            return 0.0;
        }

        $total = $this->lineItems->reduce(
            fn (float $carry, $lineItem): float => $carry + ((float) $lineItem->quantity * (float) $lineItem->estimated_unit_price),
            0.0,
        );

        return round($total, 2);
    }
}
