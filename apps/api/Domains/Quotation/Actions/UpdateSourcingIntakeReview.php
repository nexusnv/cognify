<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\SourcingIntakeReview;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateSourcingIntakeReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, SourcingIntakeReview $review, array $data): SourcingIntakeReview
    {
        if ($review->status->isTerminalForEditing()) {
            throw new ConflictHttpException('Decided sourcing intake reviews cannot be edited.');
        }

        $review->fill([
            'category' => array_key_exists('category', $data) ? $data['category'] : $review->category,
            'subcategory' => array_key_exists('subcategory', $data) ? $data['subcategory'] : $review->subcategory,
            'urgency' => array_key_exists('urgency', $data) ? $data['urgency'] : $review->urgency,
            'complexity' => array_key_exists('complexity', $data) ? $data['complexity'] : $review->complexity,
            'target_decision_date' => array_key_exists('targetDecisionDate', $data) ? $data['targetDecisionDate'] : $review->target_decision_date,
            'checklist' => array_key_exists('checklist', $data) ? $this->normalizeChecklist($data['checklist']) : $review->checklist,
            'internal_notes' => array_key_exists('internalNotes', $data) ? $data['internalNotes'] : $review->internal_notes,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'sourcing_intake.updated',
            subject: $review,
            metadata: [],
            subjectDisplay: $review->requisition?->number,
        ));

        return $review->refresh()->load(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }

    private function normalizeChecklist(mixed $items): array
    {
        return collect($items)->map(fn (array $item): array => [
            'key' => $item['key'],
            'label' => $item['label'],
            'complete' => (bool) $item['complete'],
        ])->values()->all();
    }
}
