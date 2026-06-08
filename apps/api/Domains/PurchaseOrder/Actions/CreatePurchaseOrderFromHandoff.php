<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Domains\PurchaseOrder\Support\PurchaseOrderNumber;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreatePurchaseOrderFromHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @return array{purchaseOrder: PurchaseOrder, created: bool}
     */
    public function handle(PurchaseOrderRequestHandoff $handoff, User $actor): array
    {
        return DB::transaction(function () use ($handoff, $actor): array {
            $handoff = PurchaseOrderRequestHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() === PurchaseOrderRequestHandoffStatus::Cancelled) {
                throw new ConflictHttpException('Cancelled PO handoffs cannot create purchase orders.');
            }

            if (! in_array($handoff->statusState(), [PurchaseOrderRequestHandoffStatus::Ready, PurchaseOrderRequestHandoffStatus::Exported], true)) {
                throw new ConflictHttpException('PO handoff must be ready or exported before creating a purchase order.');
            }

            $existing = PurchaseOrder::query()
                ->where('tenant_id', $handoff->tenant_id)
                ->where('purchase_order_request_handoff_id', $handoff->id)
                ->with('lines')
                ->first();

            if ($existing !== null) {
                return ['purchaseOrder' => $existing, 'created' => false];
            }

            $handoff->loadMissing('tenant');

            $purchaseOrder = PurchaseOrder::query()->create([
                'tenant_id' => $handoff->tenant_id,
                'purchase_order_request_handoff_id' => $handoff->id,
                'rfq_award_recommendation_id' => $handoff->rfq_award_recommendation_id,
                'approval_instance_id' => $handoff->approval_instance_id,
                'rfq_id' => $handoff->rfq_id,
                'requisition_id' => $handoff->requisition_id,
                'project_id' => $handoff->project_id,
                'vendor_id' => $handoff->vendor_id,
                'quotation_id' => $handoff->quotation_id,
                'quotation_version_id' => $handoff->quotation_version_id,
                'number' => PurchaseOrderNumber::next($handoff->tenant),
                'status' => PurchaseOrderStatus::Draft,
                'currency' => $handoff->currency,
                'subtotal_amount' => $handoff->subtotal_amount,
                'tax_amount' => $handoff->tax_amount,
                'freight_amount' => $handoff->freight_amount,
                'discount_amount' => $handoff->discount_amount,
                'total_amount' => $handoff->total_amount,
                'requested_po_date' => $handoff->requested_po_date,
                'delivery_attention' => $handoff->delivery_attention,
                'finance_note' => $handoff->finance_note,
                'source_snapshot' => $handoff->source_snapshot,
                'approval_snapshot' => $handoff->approval_snapshot,
                'evidence_snapshot' => $handoff->evidence_snapshot,
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);

            foreach (array_values($handoff->line_snapshot ?? []) as $index => $line) {
                PurchaseOrderLine::query()->create([
                    'tenant_id' => $handoff->tenant_id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'source_line_id' => data_get($line, 'id'),
                    'line_number' => (int) (data_get($line, 'lineNumber') ?? $index + 1),
                    'description' => (string) data_get($line, 'description', 'Purchase order line'),
                    'category' => data_get($line, 'category'),
                    'sku' => data_get($line, 'sku', data_get($line, 'itemCode')),
                    'unit' => (string) data_get($line, 'unit', data_get($line, 'unitOfMeasure', 'each')),
                    'quantity' => (string) data_get($line, 'quantity', '1'),
                    'unit_price' => (string) data_get($line, 'unitPrice', '0.00'),
                    'subtotal_amount' => (string) data_get($line, 'subtotalAmount', data_get($line, 'lineTotal', '0.00')),
                    'tax_amount' => data_get($line, 'taxAmount'),
                    'freight_amount' => data_get($line, 'freightAmount'),
                    'discount_amount' => data_get($line, 'discountAmount'),
                    'total_amount' => (string) data_get($line, 'totalAmount', data_get($line, 'lineTotal', '0.00')),
                    'currency' => $handoff->currency,
                    'needed_by_date' => data_get($line, 'neededByDate'),
                    'notes' => data_get($line, 'notes'),
                    'delivery_location' => data_get($handoff->source_snapshot, 'requisition.deliveryLocation'),
                    'source_snapshot' => $line,
                ]);
            }

            $purchaseOrder->load('lines');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'purchase_order.created',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, $handoff, [
                    'createdFromStatus' => $handoff->statusState()->value,
                ]),
                after: $purchaseOrder->toArray(),
            ));

            return ['purchaseOrder' => $purchaseOrder->fresh('lines'), 'created' => true];
        });
    }
}
