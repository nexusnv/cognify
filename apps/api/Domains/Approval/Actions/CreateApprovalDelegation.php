<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\States\ApprovalDelegationStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateApprovalDelegation
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array{delegateId:int,scope:string,startsAt:string,endsAt:string,reason:string}  $data
     */
    public function handle(Tenant $tenant, User $actor, array $data, ?ApprovalDelegation $delegation = null): ApprovalDelegation
    {
        return DB::transaction(function () use ($tenant, $actor, $data, $delegation): ApprovalDelegation {
            if ($delegation !== null) {
                $delegation = ApprovalDelegation::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($delegation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeUpdate($actor, $delegation);
            }

            $startsAt = Carbon::parse($data['startsAt']);
            $endsAt = Carbon::parse($data['endsAt']);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw ValidationException::withMessages([
                    'endsAt' => ['The end date must be after the start date.'],
                ]);
            }

            if ($endsAt->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'endsAt' => ['The end date must be in the future.'],
                ]);
            }

            if ((int) $data['delegateId'] === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'delegateId' => ['You cannot delegate to yourself.'],
                ]);
            }

            $payload = [
                'tenant_id' => $tenant->id,
                'delegator_id' => $delegation?->delegator_id ?? $actor->id,
                'delegate_id' => (int) $data['delegateId'],
                'scope' => $data['scope'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => ApprovalDelegationStatus::Active,
                'reason' => $data['reason'],
                'created_by' => $delegation?->created_by ?? $actor->id,
            ];

            if ($delegation === null) {
                $delegation = ApprovalDelegation::query()->create($payload);
                $event = 'approval_delegation.created';
            } else {
                $delegation->forceFill($payload)->save();
                $event = 'approval_delegation.updated';
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: $event,
                subject: $delegation,
                metadata: ['approvalDelegationId' => (string) $delegation->id],
            ));

            return $delegation->refresh()->load(['delegator', 'delegate', 'creator']);
        });
    }

    private function authorizeUpdate(User $actor, ApprovalDelegation $delegation): void
    {
        if ((int) $delegation->delegator_id !== (int) $actor->id) {
            throw new AuthorizationException('You can only manage your own delegations.');
        }
    }
}
