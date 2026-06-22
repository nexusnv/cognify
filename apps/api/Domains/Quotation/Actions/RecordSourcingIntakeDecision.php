<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Actions\RequestRequisitionChanges;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordSourcingIntakeDecision
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RequestRequisitionChanges $requestRequisitionChanges,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, SourcingIntakeReview $review, array $data): SourcingIntakeReview
    {
        if ($review->status !== SourcingIntakeStatus::InReview) {
            throw new ConflictHttpException('Only in-review sourcing intake reviews can receive decisions.');
        }

        $path = SourcingPath::from($data['sourcingPath']);

        if ($path === SourcingPath::NoSourcingRequired) {
            throw ValidationException::withMessages([
                'sourcingPath' => ['Use close to record no sourcing required.'],
            ]);
        }

        if ($path === SourcingPath::NeedsClarification) {
            $review->forceFill([
                'status' => SourcingIntakeStatus::ClarificationRequested,
                'sourcing_path' => $path,
                'decision_reason' => $data['decisionReason'],
                'clarification_message' => $data['clarificationMessage'] ?? null,
                'decided_at' => now(),
            ])->save();

            $this->requestRequisitionChanges->handle($tenant, $actor, $review->requisition, [
                'reason' => $data['clarificationMessage'] ?? $data['decisionReason'],
                'requestedFields' => $data['clarificationFields'] ?? [],
            ]);

            $this->auditDecision($tenant, $actor, $review, 'sourcing_intake.clarification_requested');

            return $review->refresh()->load(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
        }

        $status = $path === SourcingPath::NeedsRfq
            ? SourcingIntakeStatus::ReadyForRfq
            : SourcingIntakeStatus::DirectAwardRecorded;

        $review->forceFill([
            'status' => $status,
            'sourcing_path' => $path,
            'decision_reason' => $data['decisionReason'],
            'decided_at' => now(),
        ])->save();

        $this->auditDecision(
            $tenant,
            $actor,
            $review,
            $path === SourcingPath::NeedsRfq ? 'sourcing_intake.ready_for_rfq' : 'sourcing_intake.direct_award_recorded',
        );

        return $review->refresh()->load(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }

    private function auditDecision(Tenant $tenant, User $actor, SourcingIntakeReview $review, string $event): void
    {
        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: $event,
            subject: $review,
            metadata: ['sourcingPath' => $review->sourcing_path?->value],
            subjectDisplay: $review->requisition?->number,
        ));
    }
}
