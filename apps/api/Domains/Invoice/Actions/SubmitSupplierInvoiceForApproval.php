<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\RouteSubjectForApproval;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitSupplierInvoiceForApproval
{
    public function __construct(
        private readonly RouteSubjectForApproval $routeSubject,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(SupplierInvoice $supplierInvoice, Tenant $tenant, User $actor, int $lockVersion): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $tenant, $actor, $lockVersion) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->statusState() !== SupplierInvoiceStatus::ReadyForApproval) {
                throw new ConflictHttpException(
                    'Supplier invoice can only be submitted for approval from ready-for-approval status.',
                );
            }

            $invoice->assertLockVersion($lockVersion);

            // Delegate to shared Approval domain
            $instance = $this->routeSubject->handle($tenant, $actor, $invoice);

            $before = $invoice->only(['status', 'approval_instance_id', 'approval_submitted_by_user_id', 'approval_submitted_at', 'lock_version']);

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::InApproval,
                'approval_instance_id' => $instance->id,
                'approval_submitted_by_user_id' => $actor->id,
                'approval_submitted_at' => now(),
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.approval_submitted',
                subject: $invoice,
                metadata: [
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'approvalInstanceId' => (string) $instance->id,
                ],
                before: $before,
                after: $invoice->only(['status', 'approval_instance_id', 'approval_submitted_by_user_id', 'approval_submitted_at', 'lock_version']),
            ));

            return $invoice->fresh(['lines', 'purchaseOrder', 'vendor']);
        });
    }
}
