<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelApPaymentHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(ApPaymentHandoff $handoff, User $actor, int $lockVersion, string $reason): ApPaymentHandoff
    {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $reason): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($handoff->statusState(), [
                ApPaymentHandoffStatus::Draft,
                ApPaymentHandoffStatus::Ready,
            ], true)) {
                throw new ConflictHttpException('This AP payment handoff cannot be cancelled.');
            }

            $handoff->assertLockVersion($lockVersion);
            $reason = trim($reason);

            if ($reason === '') {
                throw new ConflictHttpException('A cancellation reason is required.');
            }

            $before = $handoff->only(['status', 'cancelled_by_user_id', 'cancelled_at', 'cancelled_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Cancelled,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.cancelled',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'cancelled_by_user_id', 'cancelled_at', 'cancelled_reason', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}
