<?php

namespace Domains\Payments\Support;

use Domains\Payments\Data\PaymentImportRowData;

class PaymentImportJsonParser
{
    public function parse(string $content): array
    {
        $payload = json_decode($content, true);
        if (! is_array($payload) || ! isset($payload['rows']) || ! is_array($payload['rows'])) {
            throw new \InvalidArgumentException('JSON import must contain a "rows" array.');
        }

        $rows = [];
        foreach ($payload['rows'] as $index => $row) {
            if (! is_array($row)) {
                $row = [];
            }
            $rows[] = new PaymentImportRowData(
                handoffNumber: $this->nullIfEmpty($row['handoffNumber'] ?? null),
                invoiceNumber: $this->nullIfEmpty($row['invoiceNumber'] ?? null),
                paymentReference: $this->nullIfEmpty($row['paymentReference'] ?? null),
                allocatedAmount: $this->nullIfEmpty($row['allocatedAmount'] ?? null),
                markFull: filter_var($row['markFull'] ?? false, FILTER_VALIDATE_BOOLEAN),
                settlementAmount: $this->nullIfEmpty($row['settlementAmount'] ?? null),
                settlementCurrency: $this->nullIfEmpty($row['settlementCurrency'] ?? null),
                paidAt: $this->nullIfEmpty($row['paidAt'] ?? null),
                settlementMethod: $this->nullIfEmpty($row['settlementMethod'] ?? null),
                status: strtolower(trim($row['status'] ?? '')),
                failureCode: $this->nullIfEmpty($row['failureCode'] ?? null),
                failureReason: $this->nullIfEmpty($row['failureReason'] ?? null),
                voidReason: $this->nullIfEmpty($row['voidReason'] ?? null),
            );
        }

        return $rows;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
