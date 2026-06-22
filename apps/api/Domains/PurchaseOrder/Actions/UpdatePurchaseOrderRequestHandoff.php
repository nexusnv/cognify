<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdatePurchaseOrderRequestHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $data
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

            $attributes = [
                'lock_version' => $handoff->lock_version + 1,
            ];

            $optionalFields = [
                'requestedPoDate' => 'requested_po_date',
                'deliveryAttention' => 'delivery_attention',
                'financeNote' => 'finance_note',
                'exportMemo' => 'export_memo',
            ];

            foreach ($optionalFields as $inputKey => $column) {
                if (Arr::exists($data, $inputKey)) {
                    $attributes[$column] = $data[$inputKey];
                }
            }

            $handoff->forceFill($attributes)->save();

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
