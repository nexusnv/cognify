<?php

namespace Domains\Receiving\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\Models\GoodsReceiptLine;
use Domains\Receiving\States\GoodsReceiptStatus;
use Domains\Receiving\Support\ReceivingNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordGoodsReceipt
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): GoodsReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): GoodsReceipt {
            $po = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($po->statusState(), [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::ChangePending], true)) {
                throw new InvalidArgumentException('Goods receipt can only be recorded for issued, acknowledged, or change-pending purchase orders.');
            }

            $po->assertLockVersion((int) $payload['lockVersion']);

            $receiptNumber = ReceivingNumber::nextFor($po);

            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $po->tenant_id,
                'purchase_order_id' => $po->id,
                'number' => $receiptNumber,
                'status' => GoodsReceiptStatus::Completed,
                'receipt_date' => $payload['receiptDate'],
                'receipt_reference' => $payload['receiptReference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => now(),
                'lock_version' => 1,
            ]);

            $linesData = [];
            $lineIds = collect($payload['lines'])->pluck('purchaseOrderLineId')->toArray();
            $poLines = PurchaseOrderLine::query()
                ->whereIn('id', $lineIds)
                ->where('tenant_id', $po->tenant_id)
                ->where('purchase_order_id', $po->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($payload['lines'] as $linePayload) {
                $poLine = $poLines->get($linePayload['purchaseOrderLineId']);

                if ($poLine === null) {
                    throw new InvalidArgumentException('Line '.$linePayload['purchaseOrderLineId'].' not found on this purchase order.');
                }

                if ($poLine->status === 'cancelled') {
                    throw new InvalidArgumentException("Line {$poLine->line_number} is cancelled and cannot receive goods.");
                }

                $quantityReceived = (float) $linePayload['quantityReceived'];
                $quantityAccepted = isset($linePayload['quantityAccepted']) ? (float) $linePayload['quantityAccepted'] : $quantityReceived;
                $newCumulativeReceived = round((float) $poLine->cumulative_quantity_received + $quantityReceived, 4);
                $orderedQuantity = (float) $poLine->quantity;
                $tolerancePercent = (float) $poLine->over_receipt_tolerance_percent;
                $maxReceivable = round($orderedQuantity + ($orderedQuantity * ($tolerancePercent / 100)), 4);

                if ($newCumulativeReceived > $maxReceivable) {
                    throw new InvalidArgumentException(
                        "Line {$poLine->line_number}: cumulative received quantity {$newCumulativeReceived} exceeds tolerance limit of {$maxReceivable}."
                    );
                }

                $newCumulativeAccepted = round((float) $poLine->cumulative_quantity_accepted + $quantityAccepted, 4);

                $linesData[] = [
                    'tenant_id' => $po->tenant_id,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $poLine->id,
                    'line_number' => $poLine->line_number,
                    'quantity_ordered' => $poLine->quantity,
                    'quantity_received' => (string) $quantityReceived,
                    'quantity_accepted' => (string) $quantityAccepted,
                    'rejection_reason' => $linePayload['rejectionReason'] ?? null,
                    'notes' => $linePayload['notes'] ?? null,
                ];

                $poLine->forceFill([
                    'cumulative_quantity_received' => (string) $newCumulativeReceived,
                    'cumulative_quantity_accepted' => (string) $newCumulativeAccepted,
                    'last_receipt_at' => now(),
                ])->save();
            }

            foreach ($linesData as $lineData) {
                GoodsReceiptLine::query()->create($lineData);
            }

            $po->forceFill(['lock_version' => $po->lock_version + 1])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $po->tenant,
                actor: $actor,
                action: 'goods_receipt.recorded',
                subject: $receipt,
                metadata: [
                    'purchaseOrderId' => (string) $po->id,
                    'purchaseOrderNumber' => $po->number,
                    'receiptNumber' => $receiptNumber,
                    'lineCount' => count($linesData),
                    'totalQuantityReceived' => array_sum(array_map(fn ($l) => (float) $l['quantity_received'], $linesData)),
                ],
            ));

            return $receipt->fresh('lines');
        });
    }
}
