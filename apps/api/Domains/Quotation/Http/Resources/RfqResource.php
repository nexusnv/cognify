<?php

namespace Domains\Quotation\Http\Resources;

use App\Audit\AuditEvent;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\RfqStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class RfqResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rfq = $this->resource;
        $status = $this->statusState();
        $canUpdate = $request->user()?->can('update', $rfq) ?? false;
        $canCancel = $request->user()?->can('cancel', $rfq) ?? false;

        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $status->value,
            'scopeSummary' => $this->scope_summary,
            'responseDueAt' => $this->response_due_at?->toISOString(),
            'responseInstructions' => $this->response_instructions,
            'requiredDocuments' => $this->required_documents ?? [],
            'lineItems' => $this->lineItems(),
            'evaluationNotes' => $this->evaluation_notes,
            'internalNotes' => $this->internal_notes,
            'cancelReason' => $this->cancel_reason,
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'intakeReview' => $this->intakeSummary($this->whenLoaded('sourcingIntakeReview')),
            'requisition' => $this->requisitionSummary($this->whenLoaded('requisition')),
            'project' => $this->projectSummary($this->whenLoaded('project')),
            'auditSummary' => $this->auditSummary(),
            'permissions' => [
                'canUpdate' => $canUpdate,
                'canCancel' => $canCancel,
                'canInviteVendors' => false,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineItems(): array
    {
        return collect($this->line_items ?? [])
            ->map(function ($lineItem): array {
                $description = data_get($lineItem, 'description');
                $name = data_get($lineItem, 'name');
                $unit = data_get($lineItem, 'unit');
                $unitOfMeasure = data_get($lineItem, 'unit_of_measure');

                return [
                    'name' => $name,
                    'description' => $description ?? $name,
                    'quantity' => data_get($lineItem, 'quantity'),
                    'unit' => $unit ?? $unitOfMeasure,
                    'notes' => data_get($lineItem, 'notes'),
                    'unitOfMeasure' => $unitOfMeasure,
                    'estimatedUnitPrice' => data_get($lineItem, 'estimated_unit_price'),
                    'currency' => data_get($lineItem, 'currency'),
                ];
            })
            ->values()
            ->all();
    }

    private function intakeSummary(mixed $review): ?array
    {
        if ($review instanceof MissingValue || $review === null) {
            return null;
        }

        return [
            'id' => (string) $review->id,
            'status' => $review->status?->value ?? $review->status,
            'sourcingPath' => $review->sourcing_path?->value ?? $review->sourcing_path,
            'decisionReason' => $review->decision_reason,
            'assignedBuyer' => $this->userSummary($review->relationLoaded('assignedBuyer') ? $review->assignedBuyer : null),
        ];
    }

    private function requisitionSummary(mixed $requisition): ?array
    {
        if ($requisition instanceof MissingValue || $requisition === null) {
            return null;
        }

        return [
            'id' => (string) $requisition->id,
            'number' => $requisition->number,
            'title' => $requisition->title,
            'status' => $requisition->status?->value ?? $requisition->status,
            'department' => $requisition->department,
            'neededByDate' => $requisition->needed_by_date?->toDateString(),
            'currency' => $requisition->currency,
            'requester' => $this->userSummary($requisition->relationLoaded('requester') ? $requisition->requester : null),
        ];
    }

    private function projectSummary(mixed $project): ?array
    {
        if ($project instanceof MissingValue || $project === null) {
            return null;
        }

        return [
            'id' => (string) $project->id,
            'number' => $project->number,
            'name' => $project->name,
            'status' => $project->status?->value ?? $project->status,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditSummary(): array
    {
        if ($this->resource === null || ! isset($this->tenant_id, $this->id)) {
            return [];
        }

        return AuditEvent::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('subject_type', Rfq::class)
            ->where('subject_id', $this->id)
            ->latest('occurred_at')
            ->latest('id')
            ->limit(10)
            ->get(['action', 'event_type', 'occurred_at', 'created_at'])
            ->map(function (AuditEvent $event): array {
                $action = $event->action ?? $event->event_type;

                return [
                    'action' => $action,
                    'eventType' => $event->event_type ?? $action,
                    'occurredAt' => $event->occurred_at?->toISOString(),
                    'createdAt' => $event->created_at?->toISOString(),
                ];
            })
            ->values()
            ->all();
    }

    private function userSummary(mixed $user): ?array
    {
        if ($user instanceof MissingValue || $user === null) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
        ];
    }
}
