<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EscalateSupplierCreditMemoException
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

            if ($lockedException->escalated_at !== null) {
                throw new ConflictHttpException('Exception is already escalated.');
            }

            $lockedException->forceFill([
                'escalated_by_user_id' => $actor->id,
                'escalated_at' => now(),
                'lock_version' => (int) $lockedException->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $lockedException->tenant,
                actor: $actor,
                action: 'supplier_credit_memo_exception.escalated',
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
