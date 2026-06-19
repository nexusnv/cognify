<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RefreshApPaymentHandoffSnapshot
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly BuildApPaymentHandoffSnapshot $buildSnapshot,
    ) {}

    /**
     * Rebuild a draft handoff's snapshot from the live invoice data.
     *
     * Only allowed while the handoff is still a draft — once it has been marked
     * ready the snapshot is locked and becomes the immutable export payload. The
     * refreshed snapshot recomputes per-invoice data, currency totals, and the
     * advisory readiness warnings.
     */
    public function handle(ApPaymentHandoff $handoff, User $actor, ?int $lockVersion = null): ApPaymentHandoff
    {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Draft) {
                throw new ConflictHttpException('Only draft AP payment handoffs can have their snapshot refreshed.');
            }

            if ($lockVersion !== null) {
                $handoff->assertLockVersion($lockVersion);
            }

            $invoices = $handoff->invoices()->with(['vendor'])->lockForUpdate()->get();

            $snapshotData = $this->buildSnapshot->handle($invoices, [
                'currency' => $handoff->currency,
                'totalAmount' => $handoff->total_amount !== null ? (string) $handoff->total_amount : null,
                'invoiceCount' => $invoices->count(),
            ]);

            $before = $handoff->only(['snapshot', 'readiness_warnings', 'lock_version']);

            $handoff->forceFill([
                'snapshot' => $snapshotData->toArray(),
                'readiness_warnings' => $snapshotData->readinessWarnings,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.snapshot_refreshed',
                subject: $handoff,
                metadata: ['warningCount' => count($snapshotData->readinessWarnings)],
                before: $before,
                after: $handoff->only(['snapshot', 'readiness_warnings', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}
