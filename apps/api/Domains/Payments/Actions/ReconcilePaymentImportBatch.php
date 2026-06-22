<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Data\ReconciliationResultData;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentFailureCode;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;

class ReconcilePaymentImportBatch
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly MatchPaymentImportRow $matcher,
        private readonly MarkApPaymentHandoffPaid $markPaid,
        private readonly MarkApPaymentHandoffFailed $markFailed,
        private readonly VoidApPaymentHandoff $voidHandoff,
        private readonly AddApPaymentAllocation $addAllocation,
        private readonly PaymentAllocationSumCalculator $calculator,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function handle(string $batchId, User $actor, ?array $lockVersions = null): ReconciliationResultData
    {
        $tenant = $this->currentTenant->get();
        if ($tenant === null) {
            throw new \RuntimeException('Tenant context missing.');
        }

        $rows = ApPaymentImport::query()
            ->where('batch_id', $batchId)
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [ApPaymentImportStatus::Pending->value, ApPaymentImportStatus::Failed->value])
            ->get();

        $reconciled = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $import) {
            DB::transaction(function () use ($import, $actor, $lockVersions, &$reconciled, &$failed, &$skipped): void {
                $import = ApPaymentImport::query()
                    ->where('tenant_id', $import->tenant_id)
                    ->whereKey($import->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($import->status !== ApPaymentImportStatus::Pending && $import->status !== ApPaymentImportStatus::Failed) {
                    $skipped++;

                    return;
                }

                if ($lockVersions !== null && ! in_array($import->lock_version, $lockVersions, true)) {
                    $import->forceFill([
                        'status' => ApPaymentImportStatus::Failed,
                        'match_error' => 'Lock version mismatch.',
                    ])->save();
                    $failed++;

                    return;
                }

                $this->matcher->handle($import);
                $import->refresh();

                if ($import->match_error !== null || $import->matched_handoff_id === null) {
                    $failed++;

                    return;
                }

                $handoff = ApPaymentHandoff::query()
                    ->where('tenant_id', $import->tenant_id)
                    ->whereKey($import->matched_handoff_id)
                    ->first();

                if ($handoff === null) {
                    $failed++;

                    return;
                }

                $target = ApPaymentImportTargetStatus::from($import->target_status);

                try {
                    match ($target) {
                        ApPaymentImportTargetStatus::Paid => $this->reconcilePaid($import, $handoff, $actor),
                        ApPaymentImportTargetStatus::Failed => $this->reconcileFailed($import, $handoff, $actor),
                        ApPaymentImportTargetStatus::Voided => $this->reconcileVoided($import, $handoff, $actor),
                    };

                    $import->forceFill([
                        'status' => ApPaymentImportStatus::Reconciled,
                        'reconciled_at' => now(),
                        'reconciled_by_user_id' => $actor->id,
                    ])->save();

                    $this->auditRecorder->record(new AuditEventData(
                        tenant: $handoff->tenant,
                        actor: $actor,
                        action: 'ap_payment_import.reconciled',
                        subject: $import,
                        metadata: ['batchId' => $import->batch_id, 'rowIndex' => $import->row_index],
                    ));

                    $reconciled++;
                } catch (\Throwable $e) {
                    $import->forceFill([
                        'status' => ApPaymentImportStatus::Failed,
                        'match_error' => $e->getMessage(),
                    ])->save();
                    $failed++;
                }
            });
        }

        return new ReconciliationResultData($reconciled, $failed, $skipped);
    }

    private function reconcilePaid(ApPaymentImport $import, ApPaymentHandoff $handoff, User $actor): void
    {
        if ($import->mark_full) {
            foreach ($handoff->invoices as $invoice) {
                $remaining = bcsub((string) $invoice->total_amount, $this->calculator->sumForInvoice($invoice), 4);
                if (bccomp($remaining, '0.0000', 4) === 1) {
                    $this->addAllocation->handle(
                        handoff: $handoff,
                        invoice: $invoice,
                        actor: $actor,
                        lockVersion: $handoff->lock_version,
                        allocatedAmount: $remaining,
                        allocationDate: $import->paid_at ?? now()->toDateString(),
                        paymentReference: $import->payment_reference,
                        settlementAmount: $import->settlement_amount,
                        settlementCurrency: $import->settlement_currency,
                    );
                    $handoff->refresh();
                }
            }
        } elseif ($import->allocated_amount !== null && $import->matched_invoice_id !== null) {
            $invoice = SupplierInvoice::query()
                ->where('tenant_id', $handoff->tenant_id)
                ->whereKey($import->matched_invoice_id)
                ->firstOrFail();
            $this->addAllocation->handle(
                handoff: $handoff,
                invoice: $invoice,
                actor: $actor,
                lockVersion: $handoff->lock_version,
                allocatedAmount: $import->allocated_amount,
                allocationDate: $import->paid_at ?? now()->toDateString(),
                paymentReference: $import->payment_reference,
                settlementAmount: $import->settlement_amount,
                settlementCurrency: $import->settlement_currency,
            );
            $handoff->refresh();
        }

        $allFullyAllocated = true;
        foreach ($handoff->invoices as $invoice) {
            if (bccomp($this->calculator->sumForInvoice($invoice), (string) $invoice->total_amount, 4) !== 0) {
                $allFullyAllocated = false;
                break;
            }
        }

        if ($allFullyAllocated) {
            $this->markPaid->handle($handoff, $actor, $handoff->lock_version);
        }
    }

    private function reconcileFailed(ApPaymentImport $import, ApPaymentHandoff $handoff, User $actor): void
    {
        $this->markFailed->handle(
            $handoff,
            $actor,
            $handoff->lock_version,
            ApPaymentFailureCode::from($import->failure_code ?? 'other'),
            $import->failure_reason ?? 'Imported failure',
        );
    }

    private function reconcileVoided(ApPaymentImport $import, ApPaymentHandoff $handoff, User $actor): void
    {
        $this->voidHandoff->handle(
            $handoff,
            $actor,
            $handoff->lock_version,
            $import->void_reason ?? 'Imported void',
        );
    }
}
