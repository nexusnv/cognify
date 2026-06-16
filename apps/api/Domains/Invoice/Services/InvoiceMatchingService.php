<?php

namespace Domains\Invoice\Services;

use Domains\Invoice\Data\InvoiceMatchResultData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Collection;

class InvoiceMatchingService
{
    public function __construct(
        private readonly ToleranceService $toleranceService,
    ) {}

    /**
     * @param SupplierInvoice $invoice
     * @param Collection<int, PurchaseOrderLine> $poLines keyed by id
     * @return array{results: InvoiceMatchResultData[], cumulativeInvoicedUpdates: array<string, string>}
     */
    public function match(
        SupplierInvoice $invoice,
        Collection $poLines,
    ): array {
        $results = [];
        $cumulativeInvoicedUpdates = [];
        $matchingPolicy = $invoice->purchaseOrder->matching_policy ?? 'three_way';

        // Header-level: vendor identity
        $results[] = $this->matchVendorIdentity($invoice);

        // Header-level: invoice total
        $results[] = $this->matchInvoiceTotal($invoice, $poLines);

        foreach ($invoice->lines as $line) {
            /** @var SupplierInvoiceLine $line */
            $pol = $poLines->get($line->purchase_order_line_id);
            if ($pol === null) {
                $results[] = new InvoiceMatchResultData(
                    dimension: 'quantity',
                    matchType: 'two_way',
                    matchLevel: 'line',
                    supplierInvoiceLineId: $line->id,
                    purchaseOrderLineId: $line->purchase_order_line_id,
                    expectedValue: null,
                    actualValue: $line->quantity_invoiced,
                    tolerancePercentApplied: null,
                    toleranceFloorApplied: null,
                    toleranceCapApplied: null,
                    result: 'not_applicable',
                    notes: 'PO line not found',
                );
                continue;
            }

            // Two-way price dimensions
            $results[] = $this->matchUnitPrice($line, $pol);
            $results[] = $this->matchLineTotal($line, $pol);
            $results[] = $this->matchTax($line, $pol);
            $results[] = $this->matchFreight($line, $pol);

            // Two-way quantity with cumulative over-billing protection
            $cumulativeInvoiced = $pol->cumulative_quantity_invoiced ?? '0.0000';
            $effectivePoQty = $pol->quantity;

            if ($pol->cancelled_by_change_order_id !== null) {
                   $effectivePoQty = '0.0000';
            }

            $qtyResult = $this->toleranceService->compareQuantity(
                $cumulativeInvoiced,
                $line->quantity_invoiced,
                (string) $effectivePoQty,
            );

            $qtyNote = $qtyResult['notes'];
            $passesQty = $qtyResult['result'] === 'pass';

            // Three-way quantity (only if policy is three_way)
            if ($matchingPolicy === 'three_way') {
                $acceptedQty = $pol->cumulative_quantity_accepted ?? '0.0000';
                $receiptResult = $this->toleranceService->compareQuantity(
                    $cumulativeInvoiced,
                    $line->quantity_invoiced,
                    (string) $acceptedQty,
                );

                if ($receiptResult['result'] !== 'pass') {
                    $passesQty = false;
                    $qtyNote = $qtyNote
                        ? $qtyNote . '; ' . $receiptResult['notes']
                        : $receiptResult['notes'];
                }

                $results[] = new InvoiceMatchResultData(
                    dimension: 'quantity',
                    matchType: 'three_way',
                    matchLevel: 'line',
                    supplierInvoiceLineId: $line->id,
                    purchaseOrderLineId: $line->purchase_order_line_id,
                    expectedValue: (string) $acceptedQty,
                    actualValue: $line->quantity_invoiced,
                    tolerancePercentApplied: 0.0,
                    toleranceFloorApplied: 0.0,
                    toleranceCapApplied: 0.0,
                    result: $receiptResult['result'],
                    notes: $receiptResult['notes'],
                );
            }

            // Two-way quantity result
            $results[] = new InvoiceMatchResultData(
                dimension: 'quantity',
                matchType: 'two_way',
                matchLevel: 'line',
                supplierInvoiceLineId: $line->id,
                purchaseOrderLineId: $line->purchase_order_line_id,
                expectedValue: (string) $effectivePoQty,
                actualValue: $line->quantity_invoiced,
                tolerancePercentApplied: 0.0,
                toleranceFloorApplied: 0.0,
                toleranceCapApplied: 0.0,
                result: $passesQty ? 'pass' : 'fail',
                notes: $qtyNote,
            );

            // Track cumulative update for this PO line
            $currentCumulative = $cumulativeInvoicedUpdates[$line->purchase_order_line_id] ?? $cumulativeInvoiced;
            $cumulativeInvoicedUpdates[$line->purchase_order_line_id] = bcadd(
                $currentCumulative,
                $line->quantity_invoiced,
                4,
            );
        }

        return [
            'results' => $results,
            'cumulativeInvoicedUpdates' => $cumulativeInvoicedUpdates,
        ];
    }

    private function matchVendorIdentity(SupplierInvoice $invoice): InvoiceMatchResultData
    {
        $pass = $invoice->vendor_id !== null
            && $invoice->purchaseOrder->vendor_id !== null
            && $invoice->vendor_id === $invoice->purchaseOrder->vendor_id;

        return new InvoiceMatchResultData(
            dimension: 'vendor_identity',
            matchType: 'two_way',
            matchLevel: 'header',
            supplierInvoiceLineId: null,
            purchaseOrderLineId: null,
            expectedValue: null,
            actualValue: null,
            tolerancePercentApplied: null,
            toleranceFloorApplied: null,
            toleranceCapApplied: null,
            result: $pass ? 'pass' : 'fail',
            notes: $pass ? null : sprintf(
                'Invoice vendor %s does not match PO vendor %s',
                $invoice->vendor_id ?? 'none',
                $invoice->purchaseOrder->vendor_id ?? 'none',
            ),
        );
    }

    private function matchInvoiceTotal(SupplierInvoice $invoice, Collection $poLines): InvoiceMatchResultData
    {
        $poTotal = '0.0000';
        foreach ($poLines as $pol) {
            $poTotal = bcadd($poTotal, (string) ($pol->total_amount ?? '0.0000'), 4);
        }

        $comparison = $this->toleranceService->compare(
            $poTotal,
            $invoice->total_amount ?? '0.0000',
            'invoice_total',
        );

        return new InvoiceMatchResultData(
            dimension: 'invoice_total',
            matchType: 'two_way',
            matchLevel: 'header',
            supplierInvoiceLineId: null,
            purchaseOrderLineId: null,
            expectedValue: $poTotal,
            actualValue: $invoice->total_amount,
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: $comparison['result'],
            notes: $comparison['notes'],
        );
    }

    private function matchUnitPrice(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        $comparison = $this->toleranceService->compare(
            (string) ($pol->unit_price ?? '0.0000'),
            $line->unit_price,
            'unit_price',
        );

        return new InvoiceMatchResultData(
            dimension: 'unit_price',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) ($pol->unit_price ?? '0.0000'),
            actualValue: $line->unit_price,
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: $comparison['result'],
            notes: $comparison['notes'],
        );
    }

    private function matchLineTotal(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        $expected = $pol->subtotal_amount ?? bcadd(
            bcmul((string) ($pol->unit_price ?? '0'), (string) ($pol->quantity ?? '0'), 4),
            '0',
            4,
        );

        $comparison = $this->toleranceService->compare(
            (string) $expected,
            $line->line_subtotal,
            'line_total',
        );

        return new InvoiceMatchResultData(
            dimension: 'line_total',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) $expected,
            actualValue: $line->line_subtotal,
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: $comparison['result'],
            notes: $comparison['notes'],
        );
    }

    private function matchTax(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        return new InvoiceMatchResultData(
            dimension: 'tax',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) ($pol->tax_amount ?? '0.0000'),
            actualValue: '0.0000',
            tolerancePercentApplied: null,
            toleranceFloorApplied: null,
            toleranceCapApplied: null,
            result: 'not_applicable',
            notes: 'Tax matching at line level not yet supported; tax is typically header-level',
        );
    }

    private function matchFreight(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        return new InvoiceMatchResultData(
            dimension: 'freight',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) ($pol->freight_amount ?? '0.0000'),
            actualValue: '0.0000',
            tolerancePercentApplied: null,
            toleranceFloorApplied: null,
            toleranceCapApplied: null,
            result: 'not_applicable',
            notes: 'Freight matching at line level not yet supported; freight is typically header-level',
        );
    }
}
