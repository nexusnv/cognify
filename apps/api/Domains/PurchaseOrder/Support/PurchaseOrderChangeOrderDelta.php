<?php

namespace Domains\PurchaseOrder\Support;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderType;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PurchaseOrderChangeOrderDelta
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{before: array<string, mixed>, after: array<string, mixed>, delta: array<string, mixed>, materialChange: bool, lineChanges: array<int, array<string, mixed>>, changeType: string}
     */
    public function calculate(PurchaseOrder $purchaseOrder, array $payload): array
    {
        $changeType = PurchaseOrderChangeOrderType::from((string) ($payload['changeType'] ?? PurchaseOrderChangeOrderType::Amendment->value));
        $purchaseOrder->loadMissing('lines');

        $before = $this->purchaseOrderSnapshot($purchaseOrder);
        $lineChanges = [];
        $afterLines = collect();
        $materialFields = [];
        $changedFields = [];
        $cancellationChange = in_array($changeType, [PurchaseOrderChangeOrderType::PartialCancellation, PurchaseOrderChangeOrderType::FullCancellation], true);

        foreach ($purchaseOrder->lines as $line) {
            $afterLine = $this->copyLineSnapshot($line);
            $linePayload = collect($payload['lines'] ?? [])->firstWhere('lineId', (string) $line->id);
            $lineAction = $linePayload['action'] ?? 'update';

            if ($cancellationChange && $changeType === PurchaseOrderChangeOrderType::FullCancellation) {
                $lineAction = 'cancel';
            }

            if ($linePayload !== null) {
                if ($lineAction === 'cancel') {
                    $afterLine['status'] = 'cancelled';
                    $afterLine['cancelledAt'] = now()->toISOString();
                    $materialFields[] = 'line.cancel';
                } else {
                    foreach (['quantity' => 'quantity', 'unitPrice' => 'unitPrice', 'expectedDeliveryDate' => 'expectedDeliveryDate', 'deliveryLocation' => 'deliveryLocation', 'notes' => 'notes'] as $inputKey => $targetKey) {
                        if (array_key_exists($inputKey, $linePayload)) {
                            $afterLine[$targetKey] = $linePayload[$inputKey];
                            if (in_array($inputKey, ['quantity', 'unitPrice'], true)) {
                                $materialFields[] = 'line.'.$inputKey;
                            }
                        }
                    }

                    $afterQuantity = (float) ($afterLine['quantity'] ?? '0');
                    $afterUnitPrice = (float) ($afterLine['unitPrice'] ?? '0');
                    $afterSubtotal = (string) number_format($afterQuantity * $afterUnitPrice, 2, '.', '');
                    $afterLine['subtotalAmount'] = $afterSubtotal;
                    $afterLine['totalAmount'] = (string) number_format(
                        (float) $afterSubtotal
                        + (float) ($afterLine['taxAmount'] ?? '0')
                        + (float) ($afterLine['freightAmount'] ?? '0')
                        - (float) ($afterLine['discountAmount'] ?? '0'),
                        2,
                        '.',
                        '',
                    );
                }

                $lineChanges[] = [
                    'lineId' => (string) $line->id,
                    'action' => $lineAction,
                    'before' => $this->copyLineSnapshot($line),
                    'after' => $afterLine,
                ];

                $afterLines->push($afterLine);
                continue;
            }

            if ($lineAction === 'cancel') {
                $afterLine['status'] = 'cancelled';
                $afterLine['cancelledAt'] = now()->toISOString();

                $lineChanges[] = [
                    'lineId' => (string) $line->id,
                    'action' => 'cancel',
                    'before' => $this->copyLineSnapshot($line),
                    'after' => $afterLine,
                ];
            }

            $afterLines->push($afterLine);
        }

        if ($changeType === PurchaseOrderChangeOrderType::FullCancellation) {
            $afterLines = collect();
        }

        $after = $this->purchaseOrderSnapshot($purchaseOrder, $payload, $afterLines);
        $changedFields = $this->changedFields($before, $after);

        if (($payload['paymentTerms'] ?? $purchaseOrder->payment_terms) !== $purchaseOrder->payment_terms) {
            $materialFields[] = 'paymentTerms';
        }

        if (($payload['deliveryTerms'] ?? $purchaseOrder->delivery_terms) !== $purchaseOrder->delivery_terms) {
            $materialFields[] = 'deliveryTerms';
        }

        if ($changeType === PurchaseOrderChangeOrderType::FullCancellation) {
            $materialFields[] = 'fullCancellation';
        }

        $delta = [
            'changedFields' => array_values(array_unique($changedFields)),
            'materialFields' => array_values(array_unique($materialFields)),
            'subtotalAmount' => ['before' => (string) $purchaseOrder->subtotal_amount, 'after' => $after['subtotalAmount']],
            'taxAmount' => ['before' => (string) $purchaseOrder->tax_amount, 'after' => $after['taxAmount']],
            'freightAmount' => ['before' => (string) $purchaseOrder->freight_amount, 'after' => $after['freightAmount']],
            'discountAmount' => ['before' => (string) $purchaseOrder->discount_amount, 'after' => $after['discountAmount']],
            'totalAmount' => ['before' => (string) $purchaseOrder->total_amount, 'after' => $after['totalAmount']],
            'lines' => $lineChanges,
        ];

        return [
            'before' => $before,
            'after' => $after,
            'delta' => $delta,
            'materialChange' => $delta['materialFields'] !== [],
            'lineChanges' => $lineChanges,
            'changeType' => $changeType->value,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $afterLines
     * @return array<string, mixed>
     */
    private function purchaseOrderSnapshot(PurchaseOrder $purchaseOrder, array $payload = [], ?Collection $afterLines = null): array
    {
        $afterLines ??= $purchaseOrder->lines->map(fn (PurchaseOrderLine $line): array => $this->copyLineSnapshot($line));
        $subtotal = '0.00';
        $tax = '0.00';
        $freight = '0.00';
        $discount = '0.00';

        foreach ($afterLines as $line) {
            if (($line['status'] ?? 'open') === 'cancelled') {
                continue;
            }

            $lineQuantity = (float) ($line['quantity'] ?? '0');
            $lineUnitPrice = (float) ($line['unitPrice'] ?? '0');
            $lineSubtotal = (float) number_format($lineQuantity * $lineUnitPrice, 2, '.', '');

            $subtotal = (string) number_format((float) $subtotal + $lineSubtotal, 2, '.', '');
            $tax = (string) number_format((float) $tax + (float) ($line['taxAmount'] ?? '0'), 2, '.', '');
            $freight = (string) number_format((float) $freight + (float) ($line['freightAmount'] ?? '0'), 2, '.', '');
            $discount = (string) number_format((float) $discount + (float) ($line['discountAmount'] ?? '0'), 2, '.', '');
        }

        $total = (string) number_format((float) $subtotal + (float) $tax + (float) $freight - (float) $discount, 2, '.', '');

        return [
            'requestedPoDate' => $payload['requestedPoDate'] ?? $purchaseOrder->requested_po_date?->toDateString(),
            'expectedDeliveryDate' => $payload['expectedDeliveryDate'] ?? $purchaseOrder->expected_delivery_date?->toDateString(),
            'billingName' => $payload['billingName'] ?? $purchaseOrder->billing_name,
            'billingAddress' => $payload['billingAddress'] ?? $purchaseOrder->billing_address,
            'shippingName' => $payload['shippingName'] ?? $purchaseOrder->shipping_name,
            'shippingAddress' => $payload['shippingAddress'] ?? $purchaseOrder->shipping_address,
            'deliveryAttention' => $payload['deliveryAttention'] ?? $purchaseOrder->delivery_attention,
            'paymentTerms' => $payload['paymentTerms'] ?? $purchaseOrder->payment_terms,
            'deliveryTerms' => $payload['deliveryTerms'] ?? $purchaseOrder->delivery_terms,
            'buyerNote' => $payload['buyerNote'] ?? $purchaseOrder->buyer_note,
            'financeNote' => $payload['financeNote'] ?? $purchaseOrder->finance_note,
            'subtotalAmount' => $subtotal,
            'taxAmount' => $tax,
            'freightAmount' => $freight,
            'discountAmount' => $discount,
            'totalAmount' => $total,
            'lines' => $afterLines->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function copyLineSnapshot(PurchaseOrderLine $line): array
    {
        return [
            'lineId' => (string) $line->id,
            'lineNumber' => $line->line_number,
            'description' => $line->description,
            'quantity' => (string) $line->quantity,
            'unitPrice' => (string) $line->unit_price,
            'subtotalAmount' => (string) $line->subtotal_amount,
            'taxAmount' => $line->tax_amount !== null ? (string) $line->tax_amount : '0.00',
            'freightAmount' => $line->freight_amount !== null ? (string) $line->freight_amount : '0.00',
            'discountAmount' => $line->discount_amount !== null ? (string) $line->discount_amount : '0.00',
            'totalAmount' => (string) $line->total_amount,
            'expectedDeliveryDate' => $line->expected_delivery_date?->toDateString(),
            'deliveryLocation' => $line->delivery_location,
            'notes' => $line->notes,
            'status' => $line->status ?? 'open',
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];

        foreach (Arr::except($after, ['lines']) as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
