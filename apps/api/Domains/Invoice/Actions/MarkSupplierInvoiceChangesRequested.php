<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkSupplierInvoiceChangesRequested
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function handle(
        SupplierInvoice $supplierInvoice,
        User $actor,
        int $lockVersion,
        string $reason,
        array $requestedFields,
    ): SupplierInvoice {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $reason, $requestedFields) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::InApproval) {
                throw new ConflictHttpException('Supplier invoice changes can only be requested from in-approval status.');
            }

            $invoice->assertLockVersion($lockVersion);

            $before = $invoice->only([
                'status', 'changes_requested_by_user_id', 'changes_requested_at',
                'changes_requested_reason', 'changes_requested_fields', 'matching_status', 'lock_version',
            ]);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::ChangesRequested,
                'changes_requested_by_user_id' => $actor->id,
                'changes_requested_at' => now(),
                'changes_requested_reason' => $reason,
                'changes_requested_fields' => $requestedFields,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            // Immediately transition to needs_information so AP user can correct
            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::NeedsInformation,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            // Invalidate cached matching results so P1-45 re-runs on re-entry
            $invoice->forceFill([
                'matching_status' => null,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.changes_requested',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'reason' => $reason,
                    'requestedFields' => $requestedFields,
                ],
                before: $before,
                after: $invoice->only([
                    'status', 'changes_requested_by_user_id', 'changes_requested_at',
                    'changes_requested_reason', 'changes_requested_fields', 'matching_status', 'lock_version',
                ]),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
