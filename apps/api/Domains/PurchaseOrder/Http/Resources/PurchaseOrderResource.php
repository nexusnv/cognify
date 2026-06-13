<?php

namespace Domains\PurchaseOrder\Http\Resources;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this->resource;
        $user = $request->user();
        $status = $purchaseOrder->statusState();
        $vendor = data_get($purchaseOrder->source_snapshot, 'vendor');

        if (! is_array($vendor)) {
            $vendor = [];
        }

        if (! array_key_exists('id', $vendor)) {
            $vendor['id'] = (string) $purchaseOrder->vendor_id;
        }

        if (! array_key_exists('name', $vendor)) {
            $vendor['name'] = null;
        }

        return [
            'id' => (string) $purchaseOrder->id,
            'number' => $purchaseOrder->number,
            'status' => $status->value,
            'currency' => $purchaseOrder->currency,
            'subtotalAmount' => (string) $purchaseOrder->subtotal_amount,
            'taxAmount' => $purchaseOrder->tax_amount !== null ? (string) $purchaseOrder->tax_amount : null,
            'freightAmount' => $purchaseOrder->freight_amount !== null ? (string) $purchaseOrder->freight_amount : null,
            'discountAmount' => $purchaseOrder->discount_amount !== null ? (string) $purchaseOrder->discount_amount : null,
            'totalAmount' => (string) $purchaseOrder->total_amount,
            'requestedPoDate' => $purchaseOrder->requested_po_date?->toDateString(),
            'expectedDeliveryDate' => $purchaseOrder->expected_delivery_date?->toDateString(),
            'billingName' => $purchaseOrder->billing_name,
            'billingAddress' => $purchaseOrder->billing_address,
            'shippingName' => $purchaseOrder->shipping_name,
            'shippingAddress' => $purchaseOrder->shipping_address,
            'deliveryAttention' => $purchaseOrder->delivery_attention,
            'paymentTerms' => $purchaseOrder->payment_terms,
            'deliveryTerms' => $purchaseOrder->delivery_terms,
            'buyerNote' => $purchaseOrder->buyer_note,
            'financeNote' => $purchaseOrder->finance_note,
            'source' => [
                'handoffId' => (string) $purchaseOrder->purchase_order_request_handoff_id,
                'recommendationId' => (string) $purchaseOrder->rfq_award_recommendation_id,
                'rfqId' => (string) $purchaseOrder->rfq_id,
                'requisitionId' => $purchaseOrder->requisition_id !== null ? (string) $purchaseOrder->requisition_id : null,
                'projectId' => $purchaseOrder->project_id !== null ? (string) $purchaseOrder->project_id : null,
                'quotationId' => $purchaseOrder->quotation_id !== null ? (string) $purchaseOrder->quotation_id : null,
                'quotationVersionId' => $purchaseOrder->quotation_version_id !== null ? (string) $purchaseOrder->quotation_version_id : null,
                'snapshot' => $purchaseOrder->source_snapshot ?? [],
            ],
            'vendor' => $vendor,
            'approval' => [
                'approvalInstanceId' => $purchaseOrder->approval_instance_id !== null ? (string) $purchaseOrder->approval_instance_id : null,
                'submittedByUserId' => $purchaseOrder->approval_submitted_by_user_id !== null ? (string) $purchaseOrder->approval_submitted_by_user_id : null,
                'submittedAt' => $purchaseOrder->approval_submitted_at?->toISOString(),
                'approvedByUserId' => $purchaseOrder->approved_by_user_id !== null ? (string) $purchaseOrder->approved_by_user_id : null,
                'approvedAt' => $purchaseOrder->approved_at?->toISOString(),
                'rejectedByUserId' => $purchaseOrder->rejected_by_user_id !== null ? (string) $purchaseOrder->rejected_by_user_id : null,
                'rejectedAt' => $purchaseOrder->rejected_at?->toISOString(),
                'rejectedReason' => $purchaseOrder->rejected_reason,
                'changesRequestedByUserId' => $purchaseOrder->changes_requested_by_user_id !== null ? (string) $purchaseOrder->changes_requested_by_user_id : null,
                'changesRequestedAt' => $purchaseOrder->changes_requested_at?->toISOString(),
                'changesRequestedReason' => $purchaseOrder->changes_requested_reason,
                'changesRequestedFields' => $purchaseOrder->changes_requested_fields ?? [],
                'snapshot' => $purchaseOrder->approval_snapshot ?? [],
            ],
            'supplierIssue' => [
                'issuedByUserId' => $purchaseOrder->issued_by_user_id !== null ? (string) $purchaseOrder->issued_by_user_id : null,
                'issuedAt' => $purchaseOrder->issued_at?->toISOString(),
                'issueMethod' => $purchaseOrder->issue_method,
                'supplierContactName' => $purchaseOrder->supplier_contact_name,
                'supplierContactEmail' => $purchaseOrder->supplier_contact_email,
                'message' => $purchaseOrder->issue_message,
                'supplierVersionNumber' => $purchaseOrder->supplier_version_number,
                'lastExportedByUserId' => $purchaseOrder->last_supplier_exported_by_user_id !== null ? (string) $purchaseOrder->last_supplier_exported_by_user_id : null,
                'lastExportedAt' => $purchaseOrder->last_supplier_exported_at?->toISOString(),
                'lastExportFormat' => $purchaseOrder->last_supplier_export_format,
                'acknowledgedByUserId' => $purchaseOrder->acknowledged_by_user_id !== null ? (string) $purchaseOrder->acknowledged_by_user_id : null,
                'acknowledgedAt' => $purchaseOrder->acknowledged_at?->toISOString(),
                'acknowledgedContactName' => $purchaseOrder->acknowledged_contact_name,
                'acknowledgementReference' => $purchaseOrder->acknowledgement_reference,
                'acknowledgementNote' => $purchaseOrder->acknowledgement_note,
            ],
            'evidence' => $purchaseOrder->evidence_snapshot ?? [],
            'lines' => $purchaseOrder->relationLoaded('lines')
                ? PurchaseOrderLineResource::collection($purchaseOrder->lines)->resolve()
                : [],
            'receivingSummary' => [
                'totalReceiptCount' => $purchaseOrder->relationLoaded('goodsReceipts')
                    ? $purchaseOrder->goodsReceipts->count()
                    : 0,
                'latestReceiptDate' => $purchaseOrder->relationLoaded('lines')
                    ? $purchaseOrder->lines->max('last_receipt_at')?->toDateString()
                    : null,
            ],
            'changeOrdersSummary' => [
                'currentChangeOrder' => $purchaseOrder->relationLoaded('currentChangeOrder') && $purchaseOrder->currentChangeOrder instanceof PurchaseOrderChangeOrder
                    ? [
                        'id' => (string) $purchaseOrder->currentChangeOrder->id,
                        'number' => $purchaseOrder->currentChangeOrder->number,
                        'status' => $purchaseOrder->currentChangeOrder->statusState()->value,
                        'changeType' => $purchaseOrder->currentChangeOrder->typeState()->value,
                        'materialChange' => (bool) $purchaseOrder->currentChangeOrder->material_change,
                        'requiresApproval' => (bool) $purchaseOrder->currentChangeOrder->requires_approval,
                    ]
                    : null,
                'latestChangeOrder' => $purchaseOrder->relationLoaded('changeOrders') && $purchaseOrder->changeOrders->isNotEmpty()
                    ? [
                        'id' => (string) $purchaseOrder->changeOrders->first()->id,
                        'number' => $purchaseOrder->changeOrders->first()->number,
                        'status' => $purchaseOrder->changeOrders->first()->statusState()->value,
                    ]
                    : null,
            ],
            'lockVersion' => $purchaseOrder->lock_version,
            'permissions' => [
                'canUpdate' => $status === PurchaseOrderStatus::Draft
                    && $user !== null
                    && Gate::forUser($user)->check('update', $purchaseOrder),
                'canMarkReadyForReview' => $status === PurchaseOrderStatus::Draft
                    && $user !== null
                    && Gate::forUser($user)->check('markReadyForReview', $purchaseOrder),
                'canCancel' => $status === PurchaseOrderStatus::Draft
                    && $user !== null
                    && Gate::forUser($user)->check('cancel', $purchaseOrder),
                'canSubmitForApproval' => in_array($status, [PurchaseOrderStatus::ReadyForReview, PurchaseOrderStatus::ChangesRequested], true)
                    && $user !== null
                    && Gate::forUser($user)->check('submitApproval', $purchaseOrder),
                'canIssueToSupplier' => $status === PurchaseOrderStatus::Approved
                    && $user !== null
                    && Gate::forUser($user)->check('issueToSupplier', $purchaseOrder),
                'canExportSupplierVersion' => in_array($status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true)
                    && $user !== null
                    && Gate::forUser($user)->check('exportSupplierVersion', $purchaseOrder),
                'canAcknowledgeSupplier' => $status === PurchaseOrderStatus::Issued
                    && $user !== null
                    && Gate::forUser($user)->check('acknowledgeSupplier', $purchaseOrder),
                'canCreateChangeOrder' => in_array($status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true)
                    && $purchaseOrder->current_change_order_id === null
                    && $user !== null
                    && Gate::forUser($user)->check('saveChangeOrder', $purchaseOrder),
                'canUpdateChangeOrder' => $purchaseOrder->current_change_order_id !== null
                    && $user !== null
                    && Gate::forUser($user)->check('saveChangeOrder', $purchaseOrder),
                'canSubmitChangeOrder' => $purchaseOrder->current_change_order_id !== null
                    && $user !== null
                    && Gate::forUser($user)->check('submitChangeOrder', $purchaseOrder),
                'canCancelChangeOrder' => $purchaseOrder->current_change_order_id !== null
                    && $user !== null
                    && Gate::forUser($user)->check('cancelChangeOrder', $purchaseOrder),
                'canCreateShipment' => in_array($status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::ChangePending], true)
                    && $user !== null
                    && Gate::forUser($user)->check('createShipment', $purchaseOrder),
                'canRecordGoodsReceipt' => in_array($status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::ChangePending], true)
                    && $user !== null
                    && Gate::forUser($user)->check('recordGoodsReceipt', $purchaseOrder),
                'canConfirmGoodsReceipt' => $user !== null
                    // Receipt-specific policies still decide whether the current actor can confirm each receipt.
                    && Gate::forUser($user)->check('recordGoodsReceipt', $purchaseOrder),
            ],
        ];
    }
}
