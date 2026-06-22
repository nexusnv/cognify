<?php

namespace Domains\Payments\Support;

use Domains\Payments\Data\PaymentImportRowData;

class PaymentImportCsvParser
{
    public function parse(string $content): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        if ($lines === []) {
            throw new \InvalidArgumentException('CSV file is empty.');
        }

        $headers = str_getcsv(array_shift($lines));
        $headerMap = array_flip(array_map('strtolower', $headers));

        if (! isset($headerMap['status'])) {
            throw new \InvalidArgumentException('Missing required CSV header: status.');
        }

        $rows = [];
        foreach ($lines as $index => $line) {
            $cols = str_getcsv($line);
            $row = [];
            foreach ($headerMap as $header => $idx) {
                $row[$header] = $cols[$idx] ?? null;
            }
            $rows[] = $this->mapToData($row);
        }

        return $rows;
    }

    private function mapToData(array $row): PaymentImportRowData
    {
        return new PaymentImportRowData(
            handoffNumber: $this->nullIfEmpty($row['handoff_number'] ?? null),
            invoiceNumber: $this->nullIfEmpty($row['invoice_number'] ?? null),
            paymentReference: $this->nullIfEmpty($row['payment_reference'] ?? null),
            allocatedAmount: $this->nullIfEmpty($row['allocated_amount'] ?? null),
            markFull: filter_var($row['mark_full'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            settlementAmount: $this->nullIfEmpty($row['settlement_amount'] ?? null),
            settlementCurrency: $this->nullIfEmpty($row['settlement_currency'] ?? null),
            paidAt: $this->nullIfEmpty($row['paid_at'] ?? null),
            settlementMethod: $this->nullIfEmpty($row['settlement_method'] ?? null),
            status: strtolower(trim($row['status'] ?? '')),
            failureCode: $this->nullIfEmpty($row['failure_code'] ?? null),
            failureReason: $this->nullIfEmpty($row['failure_reason'] ?? null),
            voidReason: $this->nullIfEmpty($row['void_reason'] ?? null),
        );
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
