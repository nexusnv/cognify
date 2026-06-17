<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EscalateInvoiceException
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreateExceptionsFromMatchResults $exceptionCreator,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        User $actor,
        User $escalatedToUser,
        ?string $note,
        int $lockVersion,
    ): SupplierInvoiceException {
        return DB::transaction(function () use ($supplierInvoice, $exception, $actor, $escalatedToUser, $note, $lockVersion) {
            $exception = SupplierInvoiceException::query()
                ->whereKey($exception->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $exception->assertLockVersion($lockVersion);

            if ($exception->status !== 'open') {
                throw new ConflictHttpException('Only open exceptions can be escalated.');
            }

            $before = $exception->only(['status', 'lock_version']);
            $exception->forceFill([
                'status' => 'escalated',
                'escalated_to_user_id' => $escalatedToUser->id,
                'escalated_by_user_id' => $actor->id,
                'escalated_at' => now(),
                'escalation_note' => $note,
                'lock_version' => $exception->lock_version + 1,
            ])->save();
            $after = $exception->only(['status', 'lock_version']);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $supplierInvoice->tenant,
                actor: $actor,
                action: 'supplier_invoice_exception.escalated',
                subject: $supplierInvoice,
                metadata: [
                    'exceptionId' => (string) $exception->id,
                    'dimension' => $exception->dimension,
                    'escalatedToUserId' => (string) $escalatedToUser->id,
                ],
                before: $before,
                after: $after,
            ));

            $this->exceptionCreator->updateExceptionSummary($supplierInvoice);

            return $exception->fresh();
        });
    }
}
