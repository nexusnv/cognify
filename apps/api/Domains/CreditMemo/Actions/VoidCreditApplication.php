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

class VoidCreditApplication
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreditApplicationSumCalculator $creditSumCalculator,
        private readonly PaymentAllocationSumCalculator $paymentSumCalculator,
    ) {}

    public function handle(
        CreditApplication $application,
        User $actor,
        int $lockVersion,
        string $voidReason,
    ): CreditApplication {
        $voidReason = trim($voidReason);
        if (strlen($voidReason) < 5) {
            throw ValidationException::withMessages([
                'voidReason' => 'Void reason must be at least 5 characters.',
            ]);
        }

        return DB::transaction(function () use ($application, $actor, $lockVersion, $voidReason): CreditApplication {
            $lockedApplication = CreditApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedApplication->lock_version !== $lockVersion) {
                throw new ConflictHttpException('Credit application was updated by another user. Refresh and try again.');
            }

            if ($lockedApplication->voided_at !== null) {
                throw new ConflictHttpException('Credit application is already voided.');
            }

            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($lockedApplication->supplier_credit_memo_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($creditMemo->statusState() === SupplierCreditMemoStatus::Voided) {
                throw new ConflictHttpException('Credit memo is voided; applications cannot be voided individually.');
            }

            $invoice = SupplierInvoice::query()
                ->whereKey($lockedApplication->supplier_invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $lockedApplication->only(['voided_at', 'voided_by_user_id', 'void_reason', 'lock_version']);

            $lockedApplication->forceFill([
                'voided_at' => now(),
                'voided_by_user_id' => $actor->id,
                'void_reason' => $voidReason,
                'lock_version' => (int) $lockedApplication->lock_version + 1,
            ])->save();

            $currentMemoStatus = $creditMemo->statusState();
            $newMemoStatus = $this->creditSumCalculator->deriveCreditMemoStatus($creditMemo);

            $creditMemo->forceFill([
                'status' => $newMemoStatus,
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            $newInvoiceStatus = $this->deriveInvoicePaymentStatusOnVoid($invoice);
            $invoiceBefore = $invoice->payment_status !== null ? $invoice->payment_status->value : null;

            if ($newInvoiceStatus !== null && $invoiceBefore !== $newInvoiceStatus->value) {
                $invoice->forceFill([
                    'payment_status' => $newInvoiceStatus,
                    'lock_version' => (int) $invoice->lock_version + 1,
                ])->save();
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.application_voided',
                subject: $creditMemo,
                metadata: [
                    'applicationId' => (string) $lockedApplication->id,
                    'number' => $creditMemo->number,
                    'voidReason' => $voidReason,
                    'fromStatus' => $currentMemoStatus->value,
                    'toStatus' => $newMemoStatus->value,
                ],
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_invoice.credit_application_voided',
                subject: $invoice,
                metadata: [
                    'applicationId' => (string) $lockedApplication->id,
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'voidReason' => $voidReason,
                    'fromStatus' => $invoiceBefore,
                    'toStatus' => $newInvoiceStatus?->value,
                ],
            ));

            return $lockedApplication->fresh();
        });
    }

    private function deriveInvoicePaymentStatusOnVoid(SupplierInvoice $invoice): ?SupplierInvoicePaymentStatus
    {
        $currentPayment = $invoice->payment_status;

        if ($currentPayment === null) {
            return null;
        }

        if ($currentPayment !== SupplierInvoicePaymentStatus::Reversed) {
            return null;
        }

        $total = (string) $invoice->total_amount;
        $newCreditApplied = $this->creditSumCalculator->sumForInvoice($invoice);

        if (bccomp($newCreditApplied, $total, 4) >= 0) {
            return SupplierInvoicePaymentStatus::Reversed;
        }

        if (bccomp($newCreditApplied, '0.0000', 4) === 0) {
            $paymentAllocated = $this->paymentSumCalculator->sumForInvoice($invoice);

            if (bccomp($paymentAllocated, $total, 4) === 0) {
                return SupplierInvoicePaymentStatus::Paid;
            }

            if (bccomp($paymentAllocated, '0.0000', 4) === 1) {
                return SupplierInvoicePaymentStatus::PartiallyPaid;
            }

            return SupplierInvoicePaymentStatus::PaymentEligible;
        }

        return SupplierInvoicePaymentStatus::PartiallyPaid;
    }
}
