<?php

namespace Domains\AccountsPayable\Actions;

use Domains\AccountsPayable\Data\ApPaymentHandoffSnapshotData;
use Domains\Invoice\Models\SupplierInvoice;

/**
 * Assembles a snapshot DTO for an AP payment handoff from a set of invoices.
 *
 * The snapshot is the immutable export payload: handoff meta, per-invoice data
 * (vendor, purchase order, totals), totals grouped by currency, and advisory
 * readiness warnings surfaced to operators before the handoff is locked.
 */
class BuildApPaymentHandoffSnapshot
{
    /**
     * @param  iterable<int, SupplierInvoice>  $invoices
     * @param  array<string, mixed>|null  $handoffMeta
     */
    public function handle(iterable $invoices, ?array $handoffMeta = null): ApPaymentHandoffSnapshotData
    {
        $invoicePayloads = [];
        $warnings = [];
        $totals = [];

        foreach ($invoices as $invoice) {
            $invoicePayloads[] = $this->invoicePayload($invoice);
            $warnings = array_merge($warnings, $this->readinessWarningsFor($invoice));

            $currency = $invoice->currency ?? 'UNKNOWN';
            $totals[$currency] = round(($totals[$currency] ?? 0) + (float) ($invoice->total_amount ?? 0), 4);
        }

        $totalByCurrency = array_map(
            static fn (string $currency, float $amount): array => [
                'currency' => $currency,
                'amount' => number_format($amount, 4, '.', ''),
            ],
            array_keys($totals),
            array_values($totals),
        );

        return new ApPaymentHandoffSnapshotData(
            handoff: $handoffMeta ?? [],
            invoices: $invoicePayloads,
            totalByCurrency: $totalByCurrency,
            readinessWarnings: array_values($this->uniqueWarnings($warnings)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(SupplierInvoice $invoice): array
    {
        return [
            'id' => (string) $invoice->id,
            'number' => $invoice->number,
            'invoiceNumber' => $invoice->invoice_number,
            'currency' => $invoice->currency,
            'totalAmount' => $invoice->total_amount !== null ? (string) $invoice->total_amount : null,
            'dueDate' => $invoice->due_date?->toDateString(),
            'vendorId' => (string) $invoice->vendor_id,
            'purchaseOrderId' => $invoice->purchase_order_id !== null ? (string) $invoice->purchase_order_id : null,
        ];
    }

    /**
     * Compute advisory readiness warnings for a single invoice.
     *
     * Warnings are informational — they surface risk to the operator but do not
     * block a handoff from being marked ready. Each warning is unique within the
     * resulting snapshot (deduplicated by its context key).
     *
     * @return array<int, array{severity: string, message: string, context: string}>
     */
    private function readinessWarningsFor(SupplierInvoice $invoice): array
    {
        $warnings = [];

        if ($invoice->due_date === null) {
            $warnings[] = [
                'severity' => 'warning',
                'message' => 'Invoice is missing a due date.',
                'context' => (string) $invoice->id,
            ];
        }

        $vendor = $invoice->vendor;
        $vendorIdentifier = $this->vendorTaxIdentifier($vendor);

        if ($vendor !== null && $vendorIdentifier === null) {
            $warnings[] = [
                'severity' => 'warning',
                'message' => 'Vendor is missing a tax/registration identifier required for remittance.',
                'context' => 'vendor:'.(string) $vendor->id,
            ];
        }

        return $warnings;
    }

    /**
     * Resolve a tax/registration identifier for a vendor, if one is stored.
     *
     * Vendors currently carry optional identifiers in their JSON metadata; this
     * keeps the snapshot resilient to vendors created without a tax id while
     * still flagging the gap when it matters for payment.
     */
    private function vendorTaxIdentifier(?object $vendor): ?string
    {
        if ($vendor === null) {
            return null;
        }

        $metadata = $vendor->metadata ?? [];

        if (is_array($metadata)) {
            foreach (['tax_id', 'tax_number', 'registration_number', 'vat_number'] as $key) {
                if (! empty($metadata[$key])) {
                    return (string) $metadata[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{severity: string, message: string, context: string}>  $warnings
     * @return array<string, array{severity: string, message: string, context: string}>
     */
    private function uniqueWarnings(array $warnings): array
    {
        $unique = [];

        foreach ($warnings as $warning) {
            $key = $warning['context'].':'.$warning['message'];
            $unique[$key] = $warning;
        }

        return $unique;
    }
}
