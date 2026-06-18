<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkApPaymentHandoffReady
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly BuildApPaymentHandoffSnapshot $buildSnapshot,
    ) {}

    public function handle(ApPaymentHandoff $handoff, User $actor, int $lockVersion): ApPaymentHandoff
    {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Draft) {
                throw new ConflictHttpException('Only draft AP payment handoffs can be marked ready.');
            }

            $handoff->assertLockVersion($lockVersion);

            $invoices = $handoff->invoices()->with(['vendor'])->lockForUpdate()->get();

            if ($invoices->isEmpty()) {
                throw new ConflictHttpException('AP payment handoff must include at least one invoice before it can be marked ready.');
            }

            // Recalculate the snapshot one final time from the live invoice data,
            // then lock it by persisting it alongside the ready transition. Readiness
            // warnings are advisory — they surface risk to the operator but do not
            // block the transition.
            $snapshotData = $this->buildSnapshot->handle($invoices, [
                'currency' => $handoff->currency,
                'totalAmount' => $handoff->total_amount !== null ? (string) $handoff->total_amount : null,
                'invoiceCount' => $invoices->count(),
            ]);

            $before = $handoff->only(['status', 'ready_by_user_id', 'ready_at', 'snapshot', 'readiness_warnings', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Ready,
                'ready_by_user_id' => $actor->id,
                'ready_at' => now(),
                'snapshot' => $snapshotData->toArray(),
                'readiness_warnings' => $snapshotData->readinessWarnings,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.ready',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'ready_by_user_id', 'ready_at', 'snapshot', 'readiness_warnings', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}
