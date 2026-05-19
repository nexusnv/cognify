<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CloseSourcingIntakeReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, SourcingIntakeReview $review, array $data): SourcingIntakeReview
    {
        if (! in_array($review->status, [
            SourcingIntakeStatus::InReview,
            SourcingIntakeStatus::ClarificationRequested,
            SourcingIntakeStatus::ReadyForRfq,
            SourcingIntakeStatus::DirectAwardRecorded,
        ], true)) {
            throw new ConflictHttpException('This sourcing intake review cannot be closed.');
        }

        $review->forceFill([
            'status' => SourcingIntakeStatus::Closed,
            'sourcing_path' => SourcingPath::NoSourcingRequired,
            'decision_reason' => $data['decisionReason'],
            'closed_at' => now(),
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'sourcing_intake.closed',
            subject: $review,
            metadata: ['sourcingPath' => SourcingPath::NoSourcingRequired->value],
            subjectDisplay: $review->requisition?->number,
        ));

        return $review->refresh()->load(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }
}
