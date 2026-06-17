<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Data\SupplierInvoiceExceptionResolutionData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResolveInvoiceException
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreateExceptionsFromMatchResults $exceptionCreator,
        private readonly RunPostResolutionMatching $postResolutionMatching,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        User $actor,
        string $resolutionType,
        ?string $adjustedValue,
        ?string $explanation,
        int $lockVersion,
    ): SupplierInvoiceException {
        return DB::transaction(function () use ($supplierInvoice, $exception, $actor, $resolutionType, $adjustedValue, $explanation, $lockVersion) {
            $exception = SupplierInvoiceException::query()
                ->whereKey($exception->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $exception->assertLockVersion($lockVersion);

            if ($exception->status === 'escalated') {
                if ((string) $actor->id !== (string) $exception->escalated_to_user_id) {
                    throw new AccessDeniedHttpException('Only the escalation target can resolve an escalated exception.');
                }
            } elseif ($exception->status !== 'open') {
                throw new ConflictHttpException('Exception is not in a resolvable state.');
            }

            $resolutionData = SupplierInvoiceExceptionResolutionData::normalize($resolutionType, $adjustedValue, $explanation);

            $before = $exception->only(['status', 'lock_version']);
            $exception->forceFill([
                'status' => 'resolved',
                'resolution_type' => $resolutionType,
                'resolution_data' => $resolutionData,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'lock_version' => $exception->lock_version + 1,
            ])->save();
            $after = $exception->only(['status', 'lock_version']);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $supplierInvoice->tenant,
                actor: $actor,
                action: 'supplier_invoice_exception.resolved',
                subject: $supplierInvoice,
                metadata: [
                    'exceptionId' => (string) $exception->id,
                    'dimension' => $exception->dimension,
                    'resolutionType' => $resolutionType,
                    'resolutionData' => $resolutionData,
                ],
                before: $before,
                after: $after,
            ));

            if ($resolutionType === 'value_adjustment' && $adjustedValue !== null) {
                $this->applyAdjustment($supplierInvoice, $exception, $adjustedValue);
            }

            $this->exceptionCreator->updateExceptionSummary($supplierInvoice);

            $this->resolveIfAllExceptionsResolved($supplierInvoice, $actor);

            return $exception->fresh();
        });
    }

    private function applyAdjustment(SupplierInvoice $invoice, SupplierInvoiceException $exception, string $adjustedValue): void
    {
        if ($exception->dimension === 'unit_price' && $exception->supplier_invoice_line_id !== null) {
            SupplierInvoiceLine::query()
                ->whereKey($exception->supplier_invoice_line_id)
                ->where('supplier_invoice_id', $invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->update([
                    'unit_price' => $adjustedValue,
                    'line_subtotal' => DB::raw("CAST(CAST(quantity_invoiced AS DECIMAL(18,4)) * CAST({$adjustedValue} AS DECIMAL(18,4)) AS TEXT)"),
                ]);
        } elseif ($exception->dimension === 'line_total' && $exception->supplier_invoice_line_id !== null) {
            SupplierInvoiceLine::query()
                ->whereKey($exception->supplier_invoice_line_id)
                ->where('supplier_invoice_id', $invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->update(['line_subtotal' => $adjustedValue]);
        }

        $total = SupplierInvoiceLine::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->sum(DB::raw('CAST(line_subtotal AS DECIMAL(18,4))'));

        SupplierInvoice::query()
            ->whereKey($invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->update(['total_amount' => (string) $total]);
    }

    private function resolveIfAllExceptionsResolved(SupplierInvoice $invoice, User $actor): void
    {
        $openCount = SupplierInvoiceException::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->where('status', '!=', 'resolved')
            ->count();

        if ($openCount > 0) {
            return;
        }

        $this->postResolutionMatching->handle($invoice, $actor);
    }
}
