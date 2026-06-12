<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Invoice\Support\SupplierInvoiceDuplicateChecker;
use Domains\Invoice\Support\SupplierInvoiceNumber;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CaptureSupplierInvoice
{
    private const MAX_DECIMAL_18_4 = '99999999999999.9999';

    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly SupplierInvoiceDuplicateChecker $duplicateChecker,
    ) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): SupplierInvoice
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): SupplierInvoice {
            $po = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($po->statusState(), [
                PurchaseOrderStatus::Issued,
                PurchaseOrderStatus::Acknowledged,
                PurchaseOrderStatus::ChangePending,
            ], true)) {
                throw new ConflictHttpException('Supplier invoices can only be captured for issued, acknowledged, or change-pending purchase orders.');
            }

            $po->assertLockVersion((int) $payload['lockVersion']);
            $this->duplicateChecker->ensureUniqueForPurchaseOrder($po, (string) $payload['invoiceNumber']);

            $lines = $payload['lines'];

            if (! is_array($lines) || count($lines) === 0) {
                throw new InvalidArgumentException('At least one line is required.');
            }

            $lineIds = [];

            foreach ($lines as $index => $linePayload) {
                $purchaseOrderLineId = $linePayload['purchaseOrderLineId'] ?? null;

                if ($purchaseOrderLineId === null) {
                    throw new InvalidArgumentException("Line at index {$index} is missing purchaseOrderLineId.");
                }

                if (in_array($purchaseOrderLineId, $lineIds, true)) {
                    throw new InvalidArgumentException("Duplicate purchase order line {$purchaseOrderLineId} in invoice lines.");
                }

                $lineIds[] = $purchaseOrderLineId;
            }

            $poLines = PurchaseOrderLine::query()
                ->whereIn('id', $lineIds)
                ->where('tenant_id', $po->tenant_id)
                ->where('purchase_order_id', $po->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $invoiceLines = [];
            $subtotalAmount = '0.0000';

            foreach ($lines as $linePayload) {
                $poLine = $poLines->get($linePayload['purchaseOrderLineId']);

                if ($poLine === null) {
                    throw new InvalidArgumentException('Line '.$linePayload['purchaseOrderLineId'].' not found on this purchase order.');
                }

                if (($poLine->status ?? 'open') === 'cancelled') {
                    throw new InvalidArgumentException("Line {$poLine->line_number} is cancelled and cannot be invoiced.");
                }

                $quantityInvoiced = (string) $linePayload['quantityInvoiced'];
                $unitPrice = (string) $linePayload['unitPrice'];

                if (bccomp($quantityInvoiced, '0', 4) <= 0) {
                    throw new InvalidArgumentException("Line {$poLine->line_number}: quantity invoiced must be greater than zero.");
                }

                if (bccomp($unitPrice, '0', 4) < 0) {
                    throw new InvalidArgumentException("Line {$poLine->line_number}: unit price cannot be negative.");
                }

                $lineSubtotalAmount = bcmul($quantityInvoiced, $unitPrice, 4);

                if (bccomp($lineSubtotalAmount, self::MAX_DECIMAL_18_4, 4) > 0) {
                    throw new InvalidArgumentException("Line {$poLine->line_number}: invoice line subtotal exceeds supported precision.");
                }

                $subtotalAmount = bcadd($subtotalAmount, $lineSubtotalAmount, 4);

                if (bccomp($subtotalAmount, self::MAX_DECIMAL_18_4, 4) > 0) {
                    throw new InvalidArgumentException("Invoice subtotal exceeds supported precision.");
                }

                $invoiceLines[] = [
                    'tenant_id' => $po->tenant_id,
                    'purchase_order_line_id' => $poLine->id,
                    'line_number' => $poLine->line_number,
                    'description_snapshot' => $poLine->description,
                    'quantity_ordered' => (string) $poLine->quantity,
                    'quantity_invoiced' => $quantityInvoiced,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotalAmount,
                    'notes' => $linePayload['notes'] ?? null,
                ];
            }

            $taxAmount = isset($payload['taxAmount']) ? bcadd((string) $payload['taxAmount'], '0', 4) : '0.0000';
            $freightAmount = isset($payload['freightAmount']) ? bcadd((string) $payload['freightAmount'], '0', 4) : '0.0000';
            $totalAmount = bcadd(bcadd($subtotalAmount, $taxAmount, 4), $freightAmount, 4);

            if (bccomp($totalAmount, self::MAX_DECIMAL_18_4, 4) > 0) {
                throw new InvalidArgumentException('Invoice total exceeds supported precision.');
            }

            $invoice = SupplierInvoice::query()->create([
                'tenant_id' => $po->tenant_id,
                'purchase_order_id' => $po->id,
                'vendor_id' => $po->vendor_id,
                'number' => SupplierInvoiceNumber::nextForTenant($po->tenant_id),
                'invoice_number' => $payload['invoiceNumber'],
                'invoice_number_normalized' => SupplierInvoiceNumber::normalize((string) $payload['invoiceNumber']),
                'status' => SupplierInvoiceStatus::Captured,
                'invoice_date' => $payload['invoiceDate'],
                'due_date' => $payload['dueDate'],
                'currency' => $po->currency,
                'subtotal_amount' => $subtotalAmount,
                'tax_amount' => $taxAmount,
                'freight_amount' => $freightAmount,
                'total_amount' => $totalAmount,
                'notes' => $payload['notes'] ?? null,
                'captured_by_user_id' => $actor->id,
                'captured_at' => now(),
                'lock_version' => 1,
            ]);

            foreach ($invoiceLines as $invoiceLine) {
                SupplierInvoiceLine::query()->create([
                    ...$invoiceLine,
                    'supplier_invoice_id' => $invoice->id,
                ]);
            }

            $po->forceFill([
                'lock_version' => $po->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $po->tenant,
                actor: $actor,
                action: 'supplier_invoice.captured',
                subject: $invoice,
                metadata: [
                    'purchaseOrderId' => (string) $po->id,
                    'purchaseOrderNumber' => $po->number,
                    'invoiceNumber' => $invoice->invoice_number,
                    'lineCount' => count($invoiceLines),
                    'totalAmount' => $totalAmount,
                ],
            ));

            return $invoice->fresh('lines');
        });
    }
}
