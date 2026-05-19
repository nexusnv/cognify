<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReassignSourcingIntakeReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Tenant $tenant, User $actor, SourcingIntakeReview $review, int $buyerId): SourcingIntakeReview
    {
        if ($review->status === SourcingIntakeStatus::Closed) {
            throw new ConflictHttpException('Closed sourcing intake reviews cannot be reassigned.');
        }

        $buyer = User::query()
            ->whereKey($buyerId)
            ->whereHas('tenants', fn ($query) => $query->whereKey($tenant->id))
            ->first();

        if ($buyer === null) {
            throw ValidationException::withMessages([
                'buyerId' => ['The selected buyer does not belong to the current tenant.'],
            ]);
        }

        $review->forceFill([
            'assigned_buyer_id' => $buyer->id,
            'claimed_at' => $review->claimed_at ?? now(),
            'status' => $review->status === SourcingIntakeStatus::Open ? SourcingIntakeStatus::InReview : $review->status,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'sourcing_intake.reassigned',
            subject: $review,
            metadata: ['assignedBuyerId' => (string) $buyer->id],
            subjectDisplay: $review->requisition?->number,
        ));

        return $review->refresh()->load(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }
}
