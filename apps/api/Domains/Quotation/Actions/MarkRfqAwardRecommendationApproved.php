<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkRfqAwardRecommendationApproved
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(RfqAwardRecommendation $recommendation, ApprovalInstance $instance, User $actor): void
    {
        $recommendation = $this->lockedRecommendation($recommendation, $instance);

        $recommendation->forceFill([
            'status' => RfqAwardRecommendationStatus::Approved,
            'approval_instance_id' => $instance->id,
            'approved_by_user_id' => $actor->id,
            'approved_at' => now(),
            'updated_by_user_id' => $actor->id,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $recommendation->tenant,
            actor: $actor,
            action: 'rfq_award_recommendation.approved',
            subject: $recommendation,
            metadata: ['approvalInstanceId' => (string) $instance->id],
        ));
    }

    private function lockedRecommendation(RfqAwardRecommendation $recommendation, ApprovalInstance $instance): RfqAwardRecommendation
    {
        $recommendation = RfqAwardRecommendation::query()
            ->where('tenant_id', $instance->tenant_id)
            ->whereKey($recommendation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($recommendation->statusState() !== RfqAwardRecommendationStatus::ApprovalRouted) {
            throw new ConflictHttpException('Only routed award recommendations can be decided.');
        }

        return $recommendation;
    }
}
