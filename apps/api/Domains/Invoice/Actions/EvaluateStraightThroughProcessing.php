<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Actions\EvaluatePaymentReadiness;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EvaluateStraightThroughProcessing
{
    public function __construct(
        private readonly MarkSupplierInvoiceApproved $markApproved,
        private readonly AuditRecorder $auditRecorder,
        private readonly EvaluatePaymentReadiness $evaluatePaymentReadiness,
    ) {}

    /**
     * Evaluate STP eligibility and auto-advance if eligible.
     * Returns true if STP was applied (invoice moved to approved).
     * Returns false if not STP-eligible (caller should submit for approval).
     */
    public function handle(SupplierInvoice $invoice, User $actor): bool
    {
        return DB::transaction(function () use ($invoice, $actor): bool {
            $invoice = SupplierInvoice::query()
                ->whereKey($invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::ReadyForApproval) {
                throw new ConflictHttpException('STP can only be evaluated on invoices in ready-for-approval status.');
            }

            $invoice->loadMissing('exceptions');

            if ($this->isStpEligible($invoice)) {
                $this->markApproved->handle(
                    $invoice,
                    actor: $actor,
                    lockVersion: (int) $invoice->lock_version,
                    isStp: true,
                );

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $invoice->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.stp_auto_approved',
                    subject: $invoice,
                    metadata: [
                        'invoiceId' => (string) $invoice->id,
                        'invoiceNumber' => $invoice->number,
                        'matchingStatus' => $invoice->matching_status,
                        'exceptionCount' => $invoice->exceptions->count(),
                    ],
                ));

                try {
                    $this->evaluatePaymentReadiness->handle($invoice, $actor);
                } catch (\Throwable $e) {
                    Log::warning('Auto-advance to payment_eligible failed (STP path)', [
                        'invoice_id' => (string) $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                return true;
            }

            return false;
        });
    }

    private function isStpEligible(SupplierInvoice $invoice): bool
    {
        if (! $invoice->relationLoaded('exceptions')) {
            $invoice->load('exceptions');
        }

        $exceptions = $invoice->exceptions;

        // Clean match: matched status and zero exceptions ever created
        if ($invoice->matching_status === SupplierInvoiceStatus::Matched->value && $exceptions->isEmpty()) {
            return true;
        }

        // Explanation-resolved: all exceptions resolved with resolution_type = explanation
        if ($exceptions->isNotEmpty()) {
            $allResolved = $exceptions->every(fn ($e) => $e->resolved_at !== null);
            $allExplanation = $exceptions->every(fn ($e) => $e->resolution_type === 'explanation');
            $anyValueAdjustment = $exceptions->contains(fn ($e) => $e->resolution_type === 'value_adjustment');

            if ($allResolved && $allExplanation && ! $anyValueAdjustment) {
                return true;
            }
        }

        return false;
    }
}
