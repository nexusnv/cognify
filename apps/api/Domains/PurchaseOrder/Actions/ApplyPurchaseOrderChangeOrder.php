<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApplyPurchaseOrderChangeOrder
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrderChangeOrder $changeOrder, User $actor, ?ApprovalInstance $instance = null): PurchaseOrderChangeOrder
    {
        return DB::transaction(function () use ($changeOrder, $actor, $instance): PurchaseOrderChangeOrder {
            $changeOrder = PurchaseOrderChangeOrder::query()
                ->whereKey($changeOrder->id)
                ->where('tenant_id', $changeOrder->tenant_id)
                ->with(['purchaseOrder.lines', 'lines'])
                ->lockForUpdate()
                ->firstOrFail();

            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($changeOrder->purchase_order_id)
                ->where('tenant_id', $changeOrder->tenant_id)
                ->with('lines')
                ->lockForUpdate()
                ->firstOrFail();

            if ($changeOrder->statusState() === PurchaseOrderChangeOrderStatus::Cancelled) {
                throw new ConflictHttpException('Cancelled change orders cannot be applied.');
            }

            $before = $purchaseOrder->only(['status', 'subtotal_amount', 'tax_amount', 'freight_amount', 'discount_amount', 'total_amount', 'current_change_order_id', 'supplier_version_number', 'current_supplier_version_number', 'lock_version']);
            $after = $changeOrder->after_snapshot ?? [];
            $restoredStatus = $changeOrder->from_purchase_order_status;

            foreach ($purchaseOrder->lines as $line) {
                $changeLine = $changeOrder->lines->firstWhere('purchase_order_line_id', $line->id);

                if (! $changeLine instanceof PurchaseOrderChangeOrderLine) {
                    continue;
                }

                if ($changeLine->change_action === 'cancel') {
                    $line->forceFill([
                        'status' => 'cancelled',
                        'cancelled_by_change_order_id' => $changeOrder->id,
                        'cancelled_at' => now(),
                        'cancelled_reason' => $changeOrder->reason,
                        'current_version_number' => ((int) ($line->current_version_number ?? 1)) + 1,
                    ])->save();
                    continue;
                }

                $line->forceFill([
                    'quantity' => $changeLine->quantity_after ?? $line->quantity,
                    'unit_price' => $changeLine->unit_price_after ?? $line->unit_price,
                    'subtotal_amount' => $changeLine->subtotal_amount_after ?? $line->subtotal_amount,
                    'tax_amount' => $changeLine->tax_amount_after ?? $line->tax_amount,
                    'freight_amount' => $changeLine->freight_amount_after ?? $line->freight_amount,
                    'discount_amount' => $changeLine->discount_amount_after ?? $line->discount_amount,
                    'total_amount' => $changeLine->total_amount_after ?? $line->total_amount,
                    'expected_delivery_date' => $changeLine->expected_delivery_date_after ?? $line->expected_delivery_date,
                    'delivery_location' => $changeLine->delivery_location_after ?? $line->delivery_location,
                    'notes' => $changeLine->notes_after ?? $line->notes,
                    'status' => 'open',
                    'current_version_number' => ((int) ($line->current_version_number ?? 1)) + 1,
                ])->save();
            }

            $priorSupplierVersion = (int) ($purchaseOrder->current_supplier_version_number ?? $purchaseOrder->supplier_version_number ?? 1);
            $supplierVersionNumber = $priorSupplierVersion + 1;
            $purchaseOrder->forceFill([
                'status' => $restoredStatus,
                'subtotal_amount' => $after['subtotalAmount'] ?? $purchaseOrder->subtotal_amount,
                'tax_amount' => $after['taxAmount'] ?? $purchaseOrder->tax_amount,
                'freight_amount' => $after['freightAmount'] ?? $purchaseOrder->freight_amount,
                'discount_amount' => $after['discountAmount'] ?? $purchaseOrder->discount_amount,
                'total_amount' => $after['totalAmount'] ?? $purchaseOrder->total_amount,
                'requested_po_date' => $after['requestedPoDate'] ?? $purchaseOrder->requested_po_date,
                'expected_delivery_date' => $after['expectedDeliveryDate'] ?? $purchaseOrder->expected_delivery_date,
                'billing_name' => $after['billingName'] ?? $purchaseOrder->billing_name,
                'billing_address' => $after['billingAddress'] ?? $purchaseOrder->billing_address,
                'shipping_name' => $after['shippingName'] ?? $purchaseOrder->shipping_name,
                'shipping_address' => $after['shippingAddress'] ?? $purchaseOrder->shipping_address,
                'delivery_attention' => $after['deliveryAttention'] ?? $purchaseOrder->delivery_attention,
                'payment_terms' => $after['paymentTerms'] ?? $purchaseOrder->payment_terms,
                'delivery_terms' => $after['deliveryTerms'] ?? $purchaseOrder->delivery_terms,
                'buyer_note' => $after['buyerNote'] ?? $purchaseOrder->buyer_note,
                'finance_note' => $after['financeNote'] ?? $purchaseOrder->finance_note,
                'current_change_order_id' => null,
                'current_supplier_version_number' => $supplierVersionNumber,
                'supplier_version_number' => $supplierVersionNumber,
                'supplier_version' => [
                    'versionNumber' => $supplierVersionNumber,
                    'purchaseOrder' => [
                        'id' => (string) $purchaseOrder->id,
                        'number' => $purchaseOrder->number,
                        'totalAmount' => $after['totalAmount'] ?? (string) $purchaseOrder->total_amount,
                        'paymentTerms' => $after['paymentTerms'] ?? $purchaseOrder->payment_terms,
                        'deliveryTerms' => $after['deliveryTerms'] ?? $purchaseOrder->delivery_terms,
                    ],
                ],
                'approval_instance_id' => $instance?->id ?? $purchaseOrder->approval_instance_id,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $changeOrder->forceFill([
                'status' => PurchaseOrderChangeOrderStatus::Approved,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'supplier_version_number' => $supplierVersionNumber,
                'to_purchase_order_status' => $restoredStatus,
                'lock_version' => $changeOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.supplier_version.superseded',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'changeOrderId' => (string) $changeOrder->id,
                    'changeOrderNumber' => $changeOrder->number,
                    'priorSupplierVersionNumber' => $priorSupplierVersion,
                    'newSupplierVersionNumber' => $supplierVersionNumber,
                    'fromStatus' => $before['status'],
                    'toStatus' => $restoredStatus,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'subtotal_amount', 'tax_amount', 'freight_amount', 'discount_amount', 'total_amount', 'current_change_order_id', 'supplier_version_number', 'current_supplier_version_number', 'lock_version']),
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.change_order.applied',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'changeOrderId' => (string) $changeOrder->id,
                    'changeOrderNumber' => $changeOrder->number,
                    'supplierVersionNumber' => $supplierVersionNumber,
                    'fromStatus' => $before['status'],
                    'toStatus' => $restoredStatus,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'subtotal_amount', 'tax_amount', 'freight_amount', 'discount_amount', 'total_amount', 'current_change_order_id', 'supplier_version_number', 'current_supplier_version_number', 'lock_version']),
            ));

            return $changeOrder->fresh(['purchaseOrder.lines', 'lines']);
        });
    }
}
