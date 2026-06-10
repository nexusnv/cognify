<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IssuePurchaseOrderToSupplier
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->with('lines')
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseOrder->statusState() !== PurchaseOrderStatus::Approved) {
                throw new ConflictHttpException('Only approved purchase orders can be issued to suppliers.');
            }

            $purchaseOrder->assertLockVersion((int) $payload['lockVersion']);
            $this->assertIssueReady($purchaseOrder);

            $before = $purchaseOrder->only(['status', 'issued_by_user_id', 'issued_at', 'lock_version']);
            $supplierVersion = $this->supplierVersion($purchaseOrder, $payload);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::Issued,
                'issued_by_user_id' => $actor->id,
                'issued_at' => now(),
                'issue_method' => (string) $payload['method'],
                'supplier_contact_name' => $payload['supplierContactName'] ?? null,
                'supplier_contact_email' => $payload['supplierContactEmail'] ?? null,
                'issue_message' => $payload['message'] ?? null,
                'supplier_version' => $supplierVersion,
                'supplier_version_number' => 1,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.issued',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'issueMethod' => (string) $payload['method'],
                    'supplierContactEmail' => $payload['supplierContactEmail'] ?? null,
                    'supplierVersionNumber' => 1,
                    'fromStatus' => PurchaseOrderStatus::Approved->value,
                    'toStatus' => PurchaseOrderStatus::Issued->value,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'issued_by_user_id', 'issued_at', 'issue_method', 'supplier_version_number', 'lock_version']),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }

    private function assertIssueReady(PurchaseOrder $purchaseOrder): void
    {
        $missing = [];

        if ($purchaseOrder->lines->isEmpty()) {
            $missing[] = 'line items';
        }

        if ($purchaseOrder->vendor_id === null || blank(data_get($purchaseOrder->source_snapshot, 'vendor.name'))) {
            $missing[] = 'vendor';
        }

        if (blank($purchaseOrder->currency) || $purchaseOrder->total_amount === null) {
            $missing[] = 'currency and total';
        }

        if (blank($purchaseOrder->payment_terms)) {
            $missing[] = 'payment terms';
        }

        if (blank($purchaseOrder->delivery_terms)) {
            $missing[] = 'delivery terms';
        }

        if (blank($purchaseOrder->shipping_name) && blank($purchaseOrder->shipping_address)) {
            $missing[] = 'shipping details';
        }

        if ($missing !== []) {
            throw new ConflictHttpException('Purchase order cannot be issued until these supplier-facing fields are complete: '.implode(', ', $missing).'.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function supplierVersion(PurchaseOrder $purchaseOrder, array $payload): array
    {
        return [
            'versionNumber' => 1,
            'issuedAt' => now()->toISOString(),
            'issueMethod' => (string) $payload['method'],
            'supplierContactName' => $payload['supplierContactName'] ?? null,
            'supplierContactEmail' => $payload['supplierContactEmail'] ?? null,
            'message' => $payload['message'] ?? null,
            'purchaseOrder' => [
                'id' => (string) $purchaseOrder->id,
                'number' => $purchaseOrder->number,
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
            ],
            'vendor' => [
                'id' => (string) $purchaseOrder->vendor_id,
                'name' => data_get($purchaseOrder->source_snapshot, 'vendor.name'),
            ],
            'lines' => $purchaseOrder->lines->map(fn ($line): array => [
                'id' => (string) $line->id,
                'lineNumber' => $line->line_number,
                'description' => $line->description,
                'category' => $line->category,
                'sku' => $line->sku,
                'quantity' => (string) $line->quantity,
                'unit' => $line->unit,
                'unitPrice' => (string) $line->unit_price,
                'taxAmount' => $line->tax_amount !== null ? (string) $line->tax_amount : null,
                'freightAmount' => $line->freight_amount !== null ? (string) $line->freight_amount : null,
                'discountAmount' => $line->discount_amount !== null ? (string) $line->discount_amount : null,
                'lineTotal' => (string) $line->total_amount,
                'currency' => $line->currency,
                'expectedDeliveryDate' => $line->expected_delivery_date?->toDateString(),
                'deliveryLocation' => $line->delivery_location,
                'notes' => $line->notes,
            ])->values()->all(),
            'source' => [
                'handoffId' => (string) $purchaseOrder->purchase_order_request_handoff_id,
                'rfqId' => (string) $purchaseOrder->rfq_id,
                'recommendationId' => (string) $purchaseOrder->rfq_award_recommendation_id,
                'requisitionId' => $purchaseOrder->requisition_id !== null ? (string) $purchaseOrder->requisition_id : null,
                'projectId' => $purchaseOrder->project_id !== null ? (string) $purchaseOrder->project_id : null,
                'quotationId' => $purchaseOrder->quotation_id !== null ? (string) $purchaseOrder->quotation_id : null,
                'quotationVersionId' => $purchaseOrder->quotation_version_id !== null ? (string) $purchaseOrder->quotation_version_id : null,
            ],
            'approval' => [
                'approvalInstanceId' => $purchaseOrder->approval_instance_id !== null ? (string) $purchaseOrder->approval_instance_id : null,
                'approvedAt' => $purchaseOrder->approved_at?->toISOString(),
                'approvedByUserId' => $purchaseOrder->approved_by_user_id !== null ? (string) $purchaseOrder->approved_by_user_id : null,
            ],
        ];
    }
}
