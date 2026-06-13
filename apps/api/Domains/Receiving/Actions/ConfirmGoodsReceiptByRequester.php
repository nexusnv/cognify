<?php

namespace Domains\Receiving\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\States\GoodsReceiptStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmGoodsReceiptByRequester
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(GoodsReceipt $receipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->whereKey($receipt->id)
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $actor->can('confirmRequester', $receipt)) {
                abort(403);
            }

            if ($receipt->statusState() !== GoodsReceiptStatus::Completed) {
                throw new InvalidArgumentException('Only completed receipts can be confirmed by the requester.');
            }

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::RequesterConfirmed,
                'requester_confirmed_by_user_id' => $actor->id,
                'requester_confirmed_at' => now(),
                'lock_version' => $receipt->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $receipt->tenant,
                actor: $actor,
                action: 'goods_receipt.requester_confirmed',
                subject: $receipt,
                metadata: [
                    'purchaseOrderId' => (string) $receipt->purchase_order_id,
                    'receiptNumber' => $receipt->number,
                ],
            ));

            return $receipt->fresh('lines');
        });
    }
}
