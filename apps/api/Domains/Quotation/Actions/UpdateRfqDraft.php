<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateRfqDraft
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): Rfq
    {
        Gate::forUser($actor)->authorize('update', $rfq);

        return DB::transaction(function () use ($tenant, $actor, $rfq, $data): Rfq {
            $lockedRfq = Rfq::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($rfq->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRfq->isEditable()) {
                throw new ConflictHttpException('Cancelled RFQs cannot be edited.');
            }

            $updates = [
                'title' => $lockedRfq->title,
                'scope_summary' => $lockedRfq->scope_summary,
                'response_due_at' => $lockedRfq->response_due_at,
                'response_instructions' => $lockedRfq->response_instructions,
                'required_documents' => $lockedRfq->required_documents,
                'line_items' => $lockedRfq->line_items,
                'evaluation_notes' => $lockedRfq->evaluation_notes,
                'internal_notes' => $lockedRfq->internal_notes,
            ];

            if (array_key_exists('title', $data)) {
                $updates['title'] = $data['title'];
            }

            if (array_key_exists('scopeSummary', $data)) {
                $updates['scope_summary'] = $data['scopeSummary'];
            }

            if (array_key_exists('responseDueAt', $data)) {
                $updates['response_due_at'] = $data['responseDueAt'];
            }

            if (array_key_exists('responseInstructions', $data)) {
                $updates['response_instructions'] = $data['responseInstructions'];
            }

            if (array_key_exists('requiredDocuments', $data)) {
                $updates['required_documents'] = $data['requiredDocuments'];
            }

            if (array_key_exists('lineItems', $data)) {
                $updates['line_items'] = $data['lineItems'];
            }

            if (array_key_exists('evaluationNotes', $data)) {
                $updates['evaluation_notes'] = $data['evaluationNotes'];
            }

            if (array_key_exists('internalNotes', $data)) {
                $updates['internal_notes'] = $data['internalNotes'];
            }

            $lockedRfq->fill($updates)->save();

            $this->audit->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq.draft_updated',
                subject: $lockedRfq,
                metadata: [],
                subjectDisplay: $lockedRfq->number,
            ));

            return $this->loadRfq($lockedRfq);
        });
    }

    private function loadRfq(Rfq $rfq): Rfq
    {
        return $rfq->refresh()->load(['sourcingIntakeReview.assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }
}
