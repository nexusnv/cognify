<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RunPostResolutionMatching
{
    public function __construct(
        private readonly RunInvoiceMatching $matchingAction,
        private readonly CreateExceptionsFromMatchResults $exceptionCreator,
        private readonly AuditRecorder $auditRecorder,
        private readonly EvaluateStraightThroughProcessing $evaluateStp,
        private readonly SubmitSupplierInvoiceForApproval $submitForApproval,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function handle(SupplierInvoice $supplierInvoice, User $actor): void
    {
        DB::transaction(function () use ($supplierInvoice, $actor) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasValueAdjustment = SupplierInvoiceException::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->where('resolution_type', 'value_adjustment')
                ->exists();

            if ($hasValueAdjustment) {
                $updatedInvoice = $this->matchingAction->handle(
                    $invoice,
                    $actor,
                    (int) $invoice->lock_version,
                    'post_resolution',
                );

                if ($updatedInvoice->matching_status === SupplierInvoiceStatus::Mismatch->value) {
                    $this->exceptionCreator->handle($updatedInvoice);

                    return;
                }

                if ($updatedInvoice->matching_status === SupplierInvoiceStatus::Matched->value) {
                    $this->transitionToReadyForApproval($updatedInvoice, $actor);
                }
            } else {
                $this->transitionToReadyForApproval($invoice, $actor);
            }
        });
    }

    private function transitionToReadyForApproval(SupplierInvoice $invoice, User $actor): void
    {
        $before = $invoice->only(['status', 'lock_version']);
        $invoice->forceFill([
            'status' => SupplierInvoiceStatus::ReadyForApproval,
            'lock_version' => $invoice->lock_version + 1,
        ])->save();
        $after = $invoice->only(['status', 'lock_version']);

        $this->auditRecorder->record(new AuditEventData(
            tenant: $invoice->tenant,
            actor: $actor,
            action: 'supplier_invoice.ready_for_approval',
            subject: $invoice,
            after: $after,
            before: $before,
        ));

        $stpApplied = $this->evaluateStp->handle($invoice, $actor);

        if (! $stpApplied) {
            $tenant = $this->currentTenant->nullable();
            if ($tenant !== null) {
                try {
                    $this->submitForApproval->handle($invoice, $tenant, $actor, (int) $invoice->lock_version);
                } catch (ConflictHttpException $e) {
                    report($e);
                }
            }
        }
    }
}
