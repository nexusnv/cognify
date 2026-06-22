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

class VoidSupplierCreditMemo
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreditApplicationSumCalculator $creditSumCalculator,
        private readonly PaymentAllocationSumCalculator $paymentSumCalculator,
    ) {}

    public function handle(
        SupplierCreditMemo $memo,
        User $actor,
        int $lockVersion,
        string $voidReason,
    ): SupplierCreditMemo {
        $voidReason = trim($voidReason);
        if (strlen($voidReason) < 5) {
            throw ValidationException::withMessages([
                'voidReason' => 'Void reason must be at least 5 characters.',
            ]);
        }

        return DB::transaction(function () use ($memo, $actor, $lockVersion, $voidReason): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()
                ->whereKey($memo->id)
                ->lockForUpdate()
                ->firstOrFail();

            $creditMemo->assertLockVersion($lockVersion);

            $voidableStates = [
                SupplierCreditMemoStatus::Draft,
                SupplierCreditMemoStatus::PendingApproval,
                SupplierCreditMemoStatus::Approved,
                SupplierCreditMemoStatus::Open,
                SupplierCreditMemoStatus::PartiallyApplied,
            ];

            if (! in_array($creditMemo->statusState(), $voidableStates, true)) {
                throw new ConflictHttpException('Credit memo cannot be voided from its current state.');
            }

            $before = $creditMemo->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']);

            $applications = CreditApplication::query()
                ->where('supplier_credit_memo_id', $creditMemo->id)
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->get();

            $applicationsVoided = 0;
            $affectedInvoiceIds = [];

            foreach ($applications as $application) {
                $application->forceFill([
                    'voided_at' => now(),
                    'voided_by_user_id' => $actor->id,
                    'void_reason' => $voidReason,
                    'lock_version' => (int) $application->lock_version + 1,
                ])->save();
                $applicationsVoided++;
                $affectedInvoiceIds[(string) $application->supplier_invoice_id] = true;
            }

            $creditMemo->forceFill([
                'status' => SupplierCreditMemoStatus::Voided,
                'voided_by_user_id' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $voidReason,
                'lock_version' => (int) $creditMemo->lock_version + 1,
            ])->save();

            foreach (array_keys($affectedInvoiceIds) as $invoiceId) {
                $invoice = SupplierInvoice::query()
                    ->whereKey($invoiceId)
                    ->lockForUpdate()
                    ->first();

                if ($invoice === null) {
                    continue;
                }

                $newStatus = $this->deriveInvoicePaymentStatusOnVoid($invoice);

                if ($newStatus !== null
                    && $invoice->payment_status !== null
                    && (string) $invoice->payment_status->value !== $newStatus->value
                ) {
                    $previous = (string) $invoice->payment_status->value;
                    $invoice->forceFill([
                        'payment_status' => $newStatus,
                        'lock_version' => (int) $invoice->lock_version + 1,
                    ])->save();

                    $this->auditRecorder->record(new AuditEventData(
                        tenant: $creditMemo->tenant,
                        actor: $actor,
                        action: 'supplier_invoice.credit_memo_voided',
                        subject: $invoice,
                        metadata: [
                            'creditMemoId' => (string) $creditMemo->id,
                            'creditMemoNumber' => $creditMemo->number,
                            'applicationsVoided' => $applicationsVoided,
                            'fromStatus' => $previous,
                            'toStatus' => $newStatus->value,
                        ],
                    ));
                }
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.voided',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'voidReason' => $voidReason,
                    'applicationsVoided' => $applicationsVoided,
                    'invoicesAffected' => count($affectedInvoiceIds),
                ],
            ));

            return $creditMemo->fresh();
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
