<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ClaimSourcingIntakeReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Tenant $tenant, User $actor, SourcingIntakeReview $review): SourcingIntakeReview
    {
        if ($review->status !== SourcingIntakeStatus::Open) {
            throw new ConflictHttpException('Only open sourcing intake reviews can be claimed.');
        }

        $review->forceFill([
            'assigned_buyer_id' => $actor->id,
            'claimed_at' => now(),
            'status' => SourcingIntakeStatus::InReview,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'sourcing_intake.claimed',
            subject: $review,
            metadata: ['assignedBuyerId' => (string) $actor->id],
            subjectDisplay: $review->requisition?->number,
        ));

        return $review->refresh()->load(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }
}
