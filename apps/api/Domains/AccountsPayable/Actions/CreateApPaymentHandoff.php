<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\AccountsPayable\Support\ApPaymentHandoffNumber;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateApPaymentHandoff
{
    public function __construct(
        private readonly ApPaymentHandoffNumber $handoffNumber,
        private readonly BuildApPaymentHandoffSnapshot $buildSnapshot,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param array<int, SupplierInvoice> $invoices
     */
    public function handle(array $invoices, User $actor, ?string $notes = null, ?string $effectivePaymentDate = null): ApPaymentHandoff
    {
        return DB::transaction(function () use ($invoices, $actor, $notes, $effectivePaymentDate): ApPaymentHandoff {
            if ($invoices === []) {
                throw new ConflictHttpException('At least one invoice is required to create an AP payment handoff.');
            }

            usort($invoices, fn ($a, $b) => $a->id <=> $b->id);

            $tenantId = null;
            $currency = null;
            $lockedInvoices = [];

            foreach ($invoices as $invoice) {
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey($invoice->id)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedInvoice->statusState() !== SupplierInvoiceStatus::Approved) {
                    throw new ConflictHttpException("Invoice {$lockedInvoice->invoice_number} is not approved.");
                }

                if ($lockedInvoice->payment_status !== SupplierInvoicePaymentStatus::PaymentEligible) {
                    throw new ConflictHttpException("Invoice {$lockedInvoice->invoice_number} is not payment eligible.");
                }

                $inActiveHandoff = ApPaymentHandoff::query()
                    ->whereHas('invoices', fn ($q) => $q->whereKey($lockedInvoice->id))
                    ->whereIn('status', [ApPaymentHandoffStatus::Draft, ApPaymentHandoffStatus::Ready, ApPaymentHandoffStatus::Exported])
                    ->exists();

                if ($inActiveHandoff) {
                    throw new ConflictHttpException("Invoice {$lockedInvoice->invoice_number} is already in an active payment handoff.");
                }

                if ($tenantId === null) {
                    $tenantId = (int) $lockedInvoice->tenant_id;
                    $currency = $lockedInvoice->currency;
                } else {
                    if ((int) $lockedInvoice->tenant_id !== $tenantId) {
                        throw new ConflictHttpException('All invoices must belong to the same tenant.');
                    }

                    if ($lockedInvoice->currency !== $currency) {
                        throw new ConflictHttpException('All invoices must use the same currency.');
                    }
                }

                $lockedInvoices[] = $lockedInvoice;
            }

            $tenant = $lockedInvoices[0]->tenant;
            $number = $this->handoffNumber->generate($tenantId);

            $totalAmount = array_reduce(
                $lockedInvoices,
                fn (string $carry, SupplierInvoice $invoice): string => bcadd($carry, (string) ($invoice->total_amount ?? '0'), 2),
                '0.00',
            );

            $snapshotData = $this->buildSnapshot->handle($lockedInvoices, [
                'currency' => $currency,
                'totalAmount' => (string) $totalAmount,
                'invoiceCount' => count($lockedInvoices),
            ]);

            $handoff = ApPaymentHandoff::query()->create([
                'tenant_id' => $tenantId,
                'number' => $number,
                'status' => ApPaymentHandoffStatus::Draft,
                'effective_payment_date' => $effectivePaymentDate,
                'notes' => $notes,
                'currency' => $currency,
                'total_amount' => $totalAmount,
                'created_by_user_id' => $actor->id,
                'snapshot' => $snapshotData->toArray(),
                'readiness_warnings' => $snapshotData->readinessWarnings,
                'lock_version' => 1,
            ]);

            $handoff->invoices()->attach(
                collect($lockedInvoices)->mapWithKeys(fn (SupplierInvoice $invoice) => [
                    $invoice->id => ['tenant_id' => $tenantId],
                ])->all()
            );

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'ap_payment_handoff.created',
                subject: $handoff,
                metadata: ['invoiceCount' => count($lockedInvoices), 'totalAmount' => (string) $totalAmount],
                after: $handoff->fresh()->toArray(),
            ));

            return $handoff->fresh();
        });
    }
}
