<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateSupplierCreditMemo
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        SupplierCreditMemo $memo,
        User $actor,
        int $lockVersion,
        ?string $notes,
        ?string $creditDate,
        ?string $vendorCreditMemoNumber,
    ): SupplierCreditMemo {
        return DB::transaction(function () use ($memo, $actor, $lockVersion, $notes, $creditDate, $vendorCreditMemoNumber): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($memo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Only draft credit memos can be updated.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            $before = $creditMemo->only(['notes', 'credit_date', 'vendor_credit_memo_number', 'lock_version']);

            $fill = [];
            if ($notes !== null) {
                $fill['notes'] = $notes;
            }
            if ($creditDate !== null) {
                $fill['credit_date'] = $creditDate;
            }
            if ($vendorCreditMemoNumber !== null) {
                $fill['vendor_credit_memo_number'] = $vendorCreditMemoNumber !== '' ? $vendorCreditMemoNumber : null;
            }

            $fill['lock_version'] = (int) $creditMemo->lock_version + 1;

            $creditMemo->forceFill($fill)->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.updated',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['notes', 'credit_date', 'vendor_credit_memo_number', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'fields' => array_keys($fill),
                ],
            ));

            return $creditMemo->fresh();
        });
    }
}
