<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcknowledgeSupplierCreditMemoException
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(SupplierCreditMemoException $exception, User $actor, int $lockVersion): SupplierCreditMemoException
    {
        return DB::transaction(function () use ($exception, $actor, $lockVersion): SupplierCreditMemoException {
            $lockedException = SupplierCreditMemoException::query()
                ->whereKey($exception->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedException->assertLockVersion($lockVersion);

            if ($lockedException->acknowledged_at !== null) {
                throw new ConflictHttpException('Exception is already acknowledged.');
            }

            $lockedException->forceFill([
                'acknowledged_by_user_id' => $actor->id,
                'acknowledged_at' => now(),
                'lock_version' => (int) $lockedException->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $lockedException->tenant,
                actor: $actor,
                action: 'supplier_credit_memo_exception.acknowledged',
                subject: $lockedException,
                metadata: [
                    'creditMemoId' => (string) $lockedException->supplier_credit_memo_id,
                    'exceptionType' => (string) $lockedException->exception_type,
                ],
            ));

            return $lockedException->fresh();
        });
    }
}
