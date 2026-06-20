<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentAllocation;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AddApPaymentAllocation
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly PaymentAllocationSumCalculator $calculator,
    ) {}

    public function handle(
        ApPaymentHandoff $handoff,
        SupplierInvoice $invoice,
        User $actor,
        int $lockVersion,
        string $allocatedAmount,
        string $allocationDate,
        ?string $paymentReference = null,
        ?string $settlementAmount = null,
        ?string $settlementCurrency = null,
    ): ApPaymentAllocation {
        return DB::transaction(function () use ($handoff, $invoice, $actor, $lockVersion, $allocatedAmount, $allocationDate, $paymentReference, $settlementAmount, $settlementCurrency): ApPaymentAllocation {
            $handoff = ApPaymentHandoff::query()
                ->where('tenant_id', $handoff->tenant_id)
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Allocations can only be added to scheduled handoffs.');
            }

            $handoff->assertLockVersion($lockVersion);

            $invoice = SupplierInvoice::query()
                ->where('tenant_id', $handoff->tenant_id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isMember = $handoff->invoices()
                ->where('supplier_invoices.id', $invoice->id)
                ->where('ap_payment_handoff_invoice.tenant_id', $handoff->tenant_id)
                ->exists();
            if (! $isMember) {
                throw new ConflictHttpException('Invoice is not a member of this handoff.');
            }

            if (bccomp($allocatedAmount, '0.0000', 4) !== 1) {
                throw ValidationException::withMessages([
                    'allocatedAmount' => 'Allocated amount must be greater than zero.',
                ]);
            }

            $currentAllocated = $this->calculator->sumForInvoice($invoice);
            $newTotal = bcadd($currentAllocated, $allocatedAmount, 4);

            if (bccomp($newTotal, (string) $invoice->total_amount, 4) === 1) {
                $remaining = bcsub((string) $invoice->total_amount, $currentAllocated, 4);
                throw ValidationException::withMessages([
                    'allocatedAmount' => "Over-allocation: current allocated {$currentAllocated}, remaining {$remaining}, attempted {$allocatedAmount}.",
                ]);
            }

            if ($settlementCurrency !== null && $settlementCurrency !== $invoice->currency) {
                if ($settlementAmount === null) {
                    throw ValidationException::withMessages([
                        'settlementAmount' => 'Settlement amount is required when settlement currency differs from invoice currency.',
                    ]);
                }
            }

            if ($settlementAmount === null) {
                $settlementAmount = $allocatedAmount;
            }

            $normalizedRef = $paymentReference !== null ? trim($paymentReference) : null;
            if ($normalizedRef === '') {
                $normalizedRef = null;
            }

            $allocation = ApPaymentAllocation::query()->create([
                'tenant_id' => $handoff->tenant_id,
                'ap_payment_handoff_id' => $handoff->id,
                'supplier_invoice_id' => $invoice->id,
                'allocated_amount' => $allocatedAmount,
                'allocation_date' => $allocationDate,
                'payment_reference' => $normalizedRef,
                'settlement_amount' => $settlementAmount,
                'settlement_currency' => $settlementCurrency,
                'lock_version' => 1,
            ]);

            $derivedStatus = $this->calculator->derivePaymentStatus($invoice);
            $invoice->forceFill([
                'payment_status' => $derivedStatus,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_allocation.created',
                subject: $allocation,
                metadata: [
                    'allocatedAmount' => $allocatedAmount,
                    'allocationDate' => $allocationDate,
                    'paymentReference' => $normalizedRef,
                    'settlementAmount' => $settlementAmount,
                    'settlementCurrency' => $settlementCurrency,
                ],
            ));

            return $allocation->fresh();
        });
    }
}
