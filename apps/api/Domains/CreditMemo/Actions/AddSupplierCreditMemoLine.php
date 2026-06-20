<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AddSupplierCreditMemoLine
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        SupplierCreditMemo $memo,
        User $actor,
        int $lockVersion,
        int $lineNumber,
        string $description,
        string $quantity,
        string $unitPrice,
        ?string $taxCode,
        string $taxAmount,
        ?string $purchaseOrderLineId,
        ?string $originalInvoiceLineId,
        ?string $notes,
    ): SupplierCreditMemoLine {
        return DB::transaction(function () use ($memo, $actor, $lockVersion, $lineNumber, $description, $quantity, $unitPrice, $taxCode, $taxAmount, $purchaseOrderLineId, $originalInvoiceLineId, $notes): SupplierCreditMemoLine {
            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($memo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Lines can only be added to draft credit memos.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            if ($purchaseOrderLineId !== null && $purchaseOrderLineId !== '') {
                $exists = \Domains\PurchaseOrder\Models\PurchaseOrderLine::query()
                    ->where('tenant_id', $creditMemo->tenant_id)
                    ->whereKey($purchaseOrderLineId)
                    ->exists();
                if (! $exists) {
                    throw ValidationException::withMessages([
                        'purchaseOrderLineId' => 'Purchase order line does not belong to the credit memo tenant.',
                    ]);
                }
            }

            if ($originalInvoiceLineId !== null && $originalInvoiceLineId !== '') {
                $exists = \Domains\Invoice\Models\SupplierInvoiceLine::query()
                    ->where('tenant_id', $creditMemo->tenant_id)
                    ->whereKey($originalInvoiceLineId)
                    ->exists();
                if (! $exists) {
                    throw ValidationException::withMessages([
                        'originalInvoiceLineId' => 'Original invoice line does not belong to the credit memo tenant.',
                    ]);
                }
            }

            $lineSubtotal = bcmul($quantity, $unitPrice, 4);

            $line = SupplierCreditMemoLine::query()->create([
                'tenant_id' => $creditMemo->tenant_id,
                'supplier_credit_memo_id' => $creditMemo->id,
                'purchase_order_line_id' => $purchaseOrderLineId !== '' ? $purchaseOrderLineId : null,
                'original_invoice_line_id' => $originalInvoiceLineId !== '' ? $originalInvoiceLineId : null,
                'line_number' => $lineNumber,
                'description_snapshot' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_code' => $taxCode !== '' ? $taxCode : null,
                'tax_amount' => $taxAmount,
                'notes' => $notes,
            ]);

            $this->recomputeHeaderTotals($creditMemo);

            $creditMemo->forceFill([
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.line_added',
                subject: $line,
                metadata: [
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'lineNumber' => $lineNumber,
                    'lineSubtotal' => $lineSubtotal,
                ],
            ));

            return $line->fresh();
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
