<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\States\ApprovalDelegationStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CancelApprovalDelegation
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, ApprovalDelegation $delegation): ApprovalDelegation
    {
        return DB::transaction(function () use ($tenant, $actor, $delegation): ApprovalDelegation {
            $delegation = ApprovalDelegation::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($delegation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeCancel($actor, $delegation);

            if ($delegation->status === ApprovalDelegationStatus::Cancelled) {
                return $delegation->refresh()->load(['delegator', 'delegate', 'creator']);
            }

            $delegation->forceFill([
                'status' => ApprovalDelegationStatus::Cancelled,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'approval_delegation.cancelled',
                subject: $delegation,
                metadata: ['approvalDelegationId' => (string) $delegation->id],
            ));

            return $delegation->refresh()->load(['delegator', 'delegate', 'creator']);
        });
    }

    private function authorizeCancel(User $actor, ApprovalDelegation $delegation): void
    {
        if ((int) $delegation->delegator_id !== (int) $actor->id) {
            throw new AuthorizationException('You can only cancel your own delegations.');
        }
    }
}
