<?php

namespace Domains\Invoice\Actions;

use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Illuminate\Support\Facades\DB;

class CreateExceptionsFromMatchResults
{
    public function handle(SupplierInvoice $invoice): void
    {
        $failResults = SupplierInvoiceMatchResult::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->where('result', 'fail')
            ->get();

        if ($failResults->isEmpty()) {
            return;
        }

        $now = now();
        $exceptions = [];

        foreach ($failResults as $result) {
            $exceptions[] = [
                'tenant_id' => $invoice->tenant_id,
                'supplier_invoice_id' => $invoice->id,
                'dimension' => $result->dimension,
                'match_type' => $result->match_type,
                'supplier_invoice_line_id' => $result->supplier_invoice_line_id,
                'purchase_order_line_id' => $result->purchase_order_line_id,
                'expected_value' => $result->expected_value,
                'actual_value' => $result->actual_value,
                'status' => 'open',
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($exceptions, $invoice): void {
            foreach ($exceptions as $exception) {
                SupplierInvoiceException::query()->firstOrCreate(
                    [
                        'tenant_id' => $exception['tenant_id'],
                        'supplier_invoice_id' => $exception['supplier_invoice_id'],
                        'dimension' => $exception['dimension'],
                        'match_type' => $exception['match_type'],
                        'supplier_invoice_line_id' => $exception['supplier_invoice_line_id'],
                    ],
                    $exception,
                );
            }

            $this->updateExceptionSummary($invoice);
        });
    }

    public function updateExceptionSummary(SupplierInvoice $invoice): void
    {
        $summary = SupplierInvoiceException::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->selectRaw("count(*) as total")
            ->selectRaw("count(case when status = 'open' then 1 end) as open")
            ->selectRaw("count(case when status = 'resolved' then 1 end) as resolved")
            ->selectRaw("count(case when status = 'escalated' then 1 end) as escalated")
            ->first();

        if ($summary !== null && $summary->total > 0) {
            $invoice->forceFill([
                'exception_summary' => [
                    'total' => (int) $summary->total,
                    'open' => (int) $summary->open,
                    'resolved' => (int) $summary->resolved,
                    'escalated' => (int) $summary->escalated,
                ],
            ])->save();
        }
    }
}
