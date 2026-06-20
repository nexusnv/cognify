<?php

namespace Domains\Payments\Actions;

use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentImportStatus;

class MatchPaymentImportRow
{
    public function handle(ApPaymentImport $import): void
    {
        if ($import->status !== ApPaymentImportStatus::Pending) {
            return;
        }

        $handoff = null;
        $invoice = null;
        $error = null;

        if ($import->handoff_number !== null) {
            $handoff = ApPaymentHandoff::query()
                ->where('tenant_id', $import->tenant_id)
                ->where('number', $import->handoff_number)
                ->first();
            if ($handoff === null) {
                $error = "Handoff {$import->handoff_number} not found.";
            }
        }

        if ($import->invoice_number !== null && $error === null) {
            $invoice = SupplierInvoice::query()
                ->where('tenant_id', $import->tenant_id)
                ->where('invoice_number', $import->invoice_number)
                ->first();
            if ($invoice === null) {
                $error = "Invoice {$import->invoice_number} not found.";
            }
        }

        if ($handoff !== null && $error === null) {
            $validStatuses = [ApPaymentHandoffStatus::Exported, ApPaymentHandoffStatus::Scheduled];
            if (! in_array($handoff->statusState(), $validStatuses, true)) {
                $error = "Handoff {$handoff->number} is not in exported or scheduled state.";
            }
        }

        if ($invoice !== null && $handoff !== null && $error === null) {
            $isMember = $handoff->invoices()->where('supplier_invoices.id', $invoice->id)->exists();
            if (! $isMember) {
                $error = "Invoice {$invoice->invoice_number} is not a member of handoff {$handoff->number}.";
            }
        }

        if ($error !== null) {
            $import->forceFill([
                'status' => ApPaymentImportStatus::Failed,
                'match_error' => $error,
                'matched_handoff_id' => null,
                'matched_invoice_id' => null,
            ])->save();
        } else {
            $import->forceFill([
                'matched_handoff_id' => $handoff?->id,
                'matched_invoice_id' => $invoice?->id,
                'match_error' => null,
            ])->save();
        }
    }
}
