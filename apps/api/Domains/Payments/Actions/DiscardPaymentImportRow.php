<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentImportStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DiscardPaymentImportRow
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(ApPaymentImport $import, User $actor): ApPaymentImport
    {
        if ($import->status === ApPaymentImportStatus::Reconciled) {
            throw new ConflictHttpException('Reconciled import rows cannot be discarded.');
        }

        $import->forceFill([
            'status' => ApPaymentImportStatus::Discarded,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $import->tenant,
            actor: $actor,
            action: 'ap_payment_import.discarded',
            subject: $import,
            metadata: ['batchId' => $import->batch_id, 'rowIndex' => $import->row_index],
        ));

        return $import->fresh();
    }
}
