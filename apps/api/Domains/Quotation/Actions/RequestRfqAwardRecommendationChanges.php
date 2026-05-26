<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RequestRfqAwardRecommendationChanges
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function handle(RfqAwardRecommendation $recommendation, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        $recommendation = RfqAwardRecommendation::query()
            ->where('tenant_id', $instance->tenant_id)
            ->whereKey($recommendation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($recommendation->statusState() !== RfqAwardRecommendationStatus::ApprovalRouted) {
            throw new ConflictHttpException('Only routed award recommendations can be decided.');
        }

        $recommendation->forceFill([
            'status' => RfqAwardRecommendationStatus::ChangesRequested,
            'approval_instance_id' => $instance->id,
            'changes_requested_by_user_id' => $actor->id,
            'changes_requested_at' => now(),
            'changes_requested_reason' => $reason,
            'changes_requested_fields' => array_values($requestedFields),
            'updated_by_user_id' => $actor->id,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $recommendation->tenant,
            actor: $actor,
            action: 'rfq_award_recommendation.changes_requested',
            subject: $recommendation,
            metadata: [
                'approvalInstanceId' => (string) $instance->id,
                'reason' => $reason,
                'requestedFields' => array_values($requestedFields),
            ],
        ));
    }
}
