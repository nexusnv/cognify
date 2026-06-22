<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateCreditApplication
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreditApplicationSumCalculator $creditSumCalculator,
        private readonly PaymentAllocationSumCalculator $paymentSumCalculator,
    ) {}

    public function handle(
        SupplierCreditMemo $memo,
        SupplierInvoice $invoice,
        User $actor,
        int $lockVersion,
        string $appliedAmount,
        string $applicationDate,
        ?string $notes,
    ): CreditApplication {
        return DB::transaction(function () use ($memo, $invoice, $actor, $lockVersion, $appliedAmount, $applicationDate, $notes): CreditApplication {
            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($memo->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInvoice = SupplierInvoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $creditMemo->assertLockVersion($lockVersion);

            if (! $creditMemo->statusState()->canAcceptCreditApplications()) {
                throw new ConflictHttpException('Credit memo must be in Open or Partially Applied state to apply credit.');
            }

            if ((int) $creditMemo->vendor_id !== (int) $lockedInvoice->vendor_id) {
                throw ValidationException::withMessages([
                    'supplierInvoiceId' => 'Credit memo and invoice must share the same vendor.',
                ]);
            }

            if (bccomp($appliedAmount, '0.0000', 4) !== 1) {
                throw ValidationException::withMessages([
                    'appliedAmount' => 'Applied amount must be greater than zero.',
                ]);
            }

            $currentMemoApplied = $this->creditSumCalculator->sumForCreditMemo($creditMemo);
            $memoTotal = (string) $creditMemo->total_amount;

            if (bccomp(bcadd($currentMemoApplied, $appliedAmount, 4), $memoTotal, 4) === 1) {
                $memoRemaining = bcsub($memoTotal, $currentMemoApplied, 4);
                throw ValidationException::withMessages([
                    'appliedAmount' => sprintf(
                        'Over-application of credit: current applied %s, remaining %s, attempted %s.',
                        $currentMemoApplied,
                        $memoRemaining,
                        $appliedAmount,
                    ),
                ]);
            }

            $invoiceTotal = (string) $lockedInvoice->total_amount;
            $existingInvoiceCredits = $this->creditSumCalculator->sumForInvoice($lockedInvoice);
            $paymentAllocated = $this->paymentSumCalculator->sumForInvoice($lockedInvoice);
            $invoiceOutstanding = bcsub(bcsub($invoiceTotal, $paymentAllocated, 4), $existingInvoiceCredits, 4);

            if (bccomp($appliedAmount, $invoiceOutstanding, 4) === 1) {
                throw ValidationException::withMessages([
                    'appliedAmount' => sprintf(
                        'Over-application of invoice: outstanding %s, attempted %s.',
                        $invoiceOutstanding,
                        $appliedAmount,
                    ),
                ]);
            }

            $invoicePaymentStatus = $lockedInvoice->payment_status;

            if ($invoicePaymentStatus !== null && ! $invoicePaymentStatus->canApplyCreditFrom()) {
                if ($invoicePaymentStatus === SupplierInvoicePaymentStatus::OnHold) {
                    throw new ConflictHttpException('Cannot apply credit to an invoice on hold. Release the hold first.');
                }
                throw new ConflictHttpException('Cannot apply credit while the invoice is in a handoff or terminal state. Void the handoff first.');
            }

            $application = CreditApplication::query()->create([
                'tenant_id' => $creditMemo->tenant_id,
                'supplier_credit_memo_id' => $creditMemo->id,
                'supplier_invoice_id' => $lockedInvoice->id,
                'applied_amount' => $appliedAmount,
                'application_date' => $applicationDate,
                'applied_by_user_id' => $actor->id,
                'notes' => $notes,
                'lock_version' => 1,
            ]);

            $derivedMemoStatus = $this->creditSumCalculator->deriveCreditMemoStatus($creditMemo);
            $creditMemoBefore = $creditMemo->only(['status', 'lock_version']);

            $newMemoStatus = $derivedMemoStatus === SupplierCreditMemoStatus::FullyApplied
                ? SupplierCreditMemoStatus::Closed
                : $derivedMemoStatus;

            $creditMemo->forceFill([
                'status' => $newMemoStatus,
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $priorInvoiceStatus = $lockedInvoice->payment_status;
            $newInvoiceStatus = $this->creditSumCalculator->deriveInvoicePaymentStatus($lockedInvoice, $priorInvoiceStatus ?? SupplierInvoicePaymentStatus::PaymentEligible);

            $invoiceBefore = $priorInvoiceStatus !== null ? $priorInvoiceStatus->value : null;
            $invoiceChanged = $priorInvoiceStatus === null || $priorInvoiceStatus->value !== $newInvoiceStatus->value;

            if ($invoiceChanged) {
                $lockedInvoice->forceFill([
                    'payment_status' => $newInvoiceStatus,
                    'lock_version' => (int) $lockedInvoice->lock_version + 1,
                ])->save();
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.applied',
                subject: $creditMemo,
                metadata: [
                    'applicationId' => (string) $application->id,
                    'number' => $creditMemo->number,
                    'appliedAmount' => $appliedAmount,
                    'invoiceId' => (string) $lockedInvoice->id,
                    'invoiceNumber' => $lockedInvoice->invoice_number,
                    'fromStatus' => $creditMemoBefore['status'],
                    'toStatus' => $newMemoStatus->value,
                ],
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_invoice.credit_applied',
                subject: $lockedInvoice,
                metadata: [
                    'applicationId' => (string) $application->id,
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'appliedAmount' => $appliedAmount,
                    'fromStatus' => $invoiceBefore,
                    'toStatus' => $newInvoiceStatus->value,
                ],
            ));

            if ($derivedMemoStatus === SupplierCreditMemoStatus::FullyApplied) {
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $creditMemo->tenant,
                    actor: $actor,
                    action: 'supplier_credit_memo.fully_applied',
                    subject: $creditMemo,
                    metadata: [
                        'applicationId' => (string) $application->id,
                        'number' => $creditMemo->number,
                    ],
                ));

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $creditMemo->tenant,
                    actor: $actor,
                    action: 'supplier_credit_memo.closed',
                    subject: $creditMemo,
                    metadata: [
                        'number' => $creditMemo->number,
                        'totalAmount' => (string) $creditMemo->total_amount,
                    ],
                ));
            }

            if ($invoiceChanged && $newInvoiceStatus === SupplierInvoicePaymentStatus::Reversed) {
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $creditMemo->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.reversed',
                    subject: $lockedInvoice,
                    metadata: [
                        'creditMemoId' => (string) $creditMemo->id,
                        'creditMemoNumber' => $creditMemo->number,
                        'applicationId' => (string) $application->id,
                    ],
                ));
            }

            return $application->fresh();
        });
    }
}
