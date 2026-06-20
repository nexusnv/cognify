<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PostSupplierCreditMemo
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(SupplierCreditMemo $memo, User $actor, int $lockVersion): SupplierCreditMemo
    {
        return DB::transaction(function () use ($memo, $actor, $lockVersion): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($memo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Approved) {
                throw new ConflictHttpException('Only approved credit memos can be posted.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            $before = $creditMemo->only(['status', 'posted_by_user_id', 'posted_at', 'lock_version']);

            $creditMemo->forceFill([
                'status' => SupplierCreditMemoStatus::Open,
                'posted_by_user_id' => $actor->id,
                'posted_at' => now(),
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.posted',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['status', 'posted_by_user_id', 'posted_at', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'totalAmount' => (string) $creditMemo->total_amount,
                    'currency' => $creditMemo->currency,
                ],
            ));

            return $creditMemo->fresh();
        });
    }
}
