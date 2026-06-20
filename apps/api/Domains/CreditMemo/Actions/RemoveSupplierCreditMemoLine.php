<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RemoveSupplierCreditMemoLine
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(SupplierCreditMemoLine $line, User $actor, int $lockVersion): void
    {
        DB::transaction(function () use ($line, $actor, $lockVersion): void {
            $lockedLine = SupplierCreditMemoLine::query()
                ->whereKey($line->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedLine->lock_version !== $lockVersion) {
                throw new ConflictHttpException('Credit memo line was updated by another user. Refresh and try again.');
            }

            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($lockedLine->supplier_credit_memo_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Lines can only be removed from draft credit memos.');
            }

            $lineNumber = $lockedLine->line_number;
            $lockedLine->delete();

            $this->recomputeHeaderTotals($creditMemo);

            $creditMemo->forceFill([
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.line_removed',
                subject: $creditMemo,
                metadata: [
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'lineNumber' => $lineNumber,
                ],
            ));
        });
    }

    private function recomputeHeaderTotals(SupplierCreditMemo $creditMemo): void
    {
        $creditMemo->load('lines');

        $subtotal = '0.0000';
        foreach ($creditMemo->lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line->line_subtotal, 4);
        }

        $tax = (string) $creditMemo->tax_amount;
        $freight = (string) $creditMemo->freight_amount;
        $total = bcadd(bcadd($subtotal, $tax, 4), $freight, 4);

        $creditMemo->forceFill([
            'subtotal_amount' => $subtotal,
            'total_amount' => $total,
        ])->save();
    }
}
