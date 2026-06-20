<?php

namespace Domains\CreditMemo\Support;

use Illuminate\Support\Facades\DB;

class SupplierCreditMemoTaxMirrorValidator
{
    /**
     * @param  array<int, array{line_number: int, tax_code: ?string, original_invoice_line_id: ?string}>  $lines
     * @return array<int, array{line_number: int, credit_tax_code: ?string, original_tax_code: ?string}>
     */
    public function validate(string $tenantId, string $originalInvoiceId, array $lines): array
    {
        $originalLineIds = array_values(array_filter(
            array_map(fn (array $line) => $line['original_invoice_line_id'] ?? null, $lines),
        ));

        if ($originalLineIds === []) {
            return [];
        }

        $originalTaxCodes = DB::table('supplier_invoice_lines')
            ->where('tenant_id', $tenantId)
            ->where('supplier_invoice_id', $originalInvoiceId)
            ->whereIn('id', $originalLineIds)
            ->pluck('tax_code', 'id');

        $mismatches = [];

        foreach ($lines as $line) {
            $originalInvoiceLineId = $line['original_invoice_line_id'] ?? null;

            if ($originalInvoiceLineId === null) {
                continue;
            }

            $creditTaxCode = $line['tax_code'] ?? null;
            $originalTaxCode = $originalTaxCodes->get($originalInvoiceLineId);

            if ($creditTaxCode !== $originalTaxCode) {
                $mismatches[] = [
                    'line_number' => $line['line_number'],
                    'credit_tax_code' => $creditTaxCode,
                    'original_tax_code' => $originalTaxCode,
                ];
            }
        }

        return $mismatches;
    }
}
