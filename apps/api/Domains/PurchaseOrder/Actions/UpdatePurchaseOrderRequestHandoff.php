<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdatePurchaseOrderRequestHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(PurchaseOrderRequestHandoff $handoff, User $actor, array $data): PurchaseOrderRequestHandoff
    {
        return DB::transaction(function () use ($handoff, $actor, $data): PurchaseOrderRequestHandoff {
            $handoff = PurchaseOrderRequestHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() !== PurchaseOrderRequestHandoffStatus::Draft) {
                throw new ConflictHttpException('Only draft PO handoffs can be updated.');
            }

            $handoff->assertLockVersion((int) Arr::get($data, 'lockVersion'));
            $before = $handoff->only(['requested_po_date', 'delivery_attention', 'finance_note', 'export_memo', 'lock_version']);

            $handoff->forceFill([
                'requested_po_date' => Arr::get($data, 'requestedPoDate'),
                'delivery_attention' => Arr::get($data, 'deliveryAttention'),
                'finance_note' => Arr::get($data, 'financeNote'),
                'export_memo' => Arr::get($data, 'exportMemo'),
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'purchase_order_handoff.updated',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['requested_po_date', 'delivery_attention', 'finance_note', 'export_memo', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}
