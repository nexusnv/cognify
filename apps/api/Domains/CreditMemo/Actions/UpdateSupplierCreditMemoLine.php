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

class UpdateSupplierCreditMemoLine
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        SupplierCreditMemoLine $line,
        User $actor,
        int $lockVersion,
        ?string $description,
        ?string $quantity,
        ?string $unitPrice,
        ?string $taxCode,
        ?string $taxAmount,
        ?string $notes,
    ): SupplierCreditMemoLine {
        return DB::transaction(function () use ($line, $actor, $lockVersion, $description, $quantity, $unitPrice, $taxCode, $taxAmount, $notes): SupplierCreditMemoLine {
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
                throw new ConflictHttpException('Lines can only be updated on draft credit memos.');
            }

            $fill = [];
            if ($description !== null) {
                $fill['description_snapshot'] = $description;
            }
            if ($quantity !== null) {
                $fill['quantity'] = $quantity;
            }
            if ($unitPrice !== null) {
                $fill['unit_price'] = $unitPrice;
            }
            if ($taxCode !== null) {
                $fill['tax_code'] = $taxCode !== '' ? $taxCode : null;
            }
            if ($taxAmount !== null) {
                $fill['tax_amount'] = $taxAmount;
            }
            if ($notes !== null) {
                $fill['notes'] = $notes;
            }

            if (isset($fill['quantity']) || isset($fill['unit_price'])) {
                $newQuantity = $fill['quantity'] ?? (string) $lockedLine->quantity;
                $newUnitPrice = $fill['unit_price'] ?? (string) $lockedLine->unit_price;
                $fill['line_subtotal'] = bcmul($newQuantity, $newUnitPrice, 4);
            }

            $fill['lock_version'] = (int) $lockedLine->lock_version + 1;

            $lockedLine->forceFill($fill)->save();

            $this->recomputeHeaderTotals($creditMemo);

            $creditMemo->forceFill([
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.line_updated',
                subject: $lockedLine,
                metadata: [
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'lineNumber' => $lockedLine->line_number,
                ],
            ));

            return $lockedLine->fresh();
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
