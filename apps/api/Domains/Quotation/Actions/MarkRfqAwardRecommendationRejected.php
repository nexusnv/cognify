<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkRfqAwardRecommendationRejected
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(RfqAwardRecommendation $recommendation, ApprovalInstance $instance, User $actor, string $reason): void
    {
        $recommendation = RfqAwardRecommendation::query()
            ->where('tenant_id', $instance->tenant_id)
            ->whereKey($recommendation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $recommendation->approval_instance_id !== (int) $instance->id) {
            throw new ConflictHttpException('Approval instance does not match routed recommendation.');
        }

        if ($recommendation->statusState() !== RfqAwardRecommendationStatus::ApprovalRouted) {
            throw new ConflictHttpException('Only routed award recommendations can be decided.');
        }

        $recommendation->forceFill([
            'status' => RfqAwardRecommendationStatus::Rejected,
            'approval_instance_id' => $instance->id,
            'rejected_by_user_id' => $actor->id,
            'rejected_at' => now(),
            'decision_reason' => $reason,
            'updated_by_user_id' => $actor->id,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $recommendation->tenant,
            actor: $actor,
            action: 'rfq_award_recommendation.rejected',
            subject: $recommendation,
            metadata: [
                'approvalInstanceId' => (string) $instance->id,
                'reason' => $reason,
            ],
        ));
    }
}
