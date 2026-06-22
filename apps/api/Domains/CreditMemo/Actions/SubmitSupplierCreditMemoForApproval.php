<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Actions\RouteSubjectForApproval;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionSeverity;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitSupplierCreditMemoForApproval
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RouteSubjectForApproval $routeSubjectForApproval,
    ) {}

    public function handle(SupplierCreditMemo $memo, User $actor, int $lockVersion): SupplierCreditMemo
    {
        return DB::transaction(function () use ($memo, $actor, $lockVersion): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($memo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Only draft credit memos can be submitted for approval.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            if ($creditMemo->lines()->count() === 0) {
                throw new ConflictHttpException('Credit memo must have at least one line.');
            }

            $openBlocking = $creditMemo->exceptions()
                ->whereNull('resolved_at')
                ->where('severity', SupplierCreditMemoExceptionSeverity::Blocking->value)
                ->count();

            if ($openBlocking > 0) {
                throw new ConflictHttpException("Credit memo has {$openBlocking} open blocking exceptions.");
            }

            $before = $creditMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'lock_version']);

            $creditMemo->forceFill([
                'status' => SupplierCreditMemoStatus::PendingApproval,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $instance = $this->routeSubjectForApproval->handle($creditMemo->tenant, $actor, $creditMemo);

            $creditMemo->forceFill([
                'approval_instance_id' => $instance->id,
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.submitted_for_approval',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'approvalInstanceId' => (string) $instance->id,
                ],
            ));

            return $creditMemo->fresh();
        });
    }
}
