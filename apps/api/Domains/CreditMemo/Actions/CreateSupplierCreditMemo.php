<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionSeverity;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionType;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\SupplierCreditMemoDuplicateDetector;
use Domains\CreditMemo\Support\SupplierCreditMemoNumberGenerator;
use Domains\CreditMemo\Support\SupplierCreditMemoTaxMirrorValidator;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Vendor\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateSupplierCreditMemo
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly SupplierCreditMemoNumberGenerator $numberGenerator,
        private readonly SupplierCreditMemoDuplicateDetector $duplicateDetector,
        private readonly SupplierCreditMemoTaxMirrorValidator $taxMirrorValidator,
    ) {}

    /**
     * @param  array<int, array{line_number: int, description: string, quantity: string, unit_price: string, tax_code: ?string, tax_amount: ?string, purchase_order_line_id: ?string, original_invoice_line_id: ?string, notes: ?string}>  $lines
     */
    public function handle(
        Tenant $tenant,
        User $actor,
        int $vendorId,
        ?string $originalInvoiceId,
        string $vendorCreditMemoNumber,
        ?string $creditDate,
        string $currency,
        string $subtotal,
        string $tax,
        string $freight,
        string $total,
        array $lines,
        ?string $notes,
    ): SupplierCreditMemo {
        return DB::transaction(function () use ($tenant, $actor, $vendorId, $originalInvoiceId, $vendorCreditMemoNumber, $creditDate, $currency, $subtotal, $tax, $freight, $total, $lines, $notes): SupplierCreditMemo {
            $vendor = Vendor::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($vendorId)
                ->lockForUpdate()
                ->firstOrFail();

            $originalInvoice = null;
            if ($originalInvoiceId !== null && $originalInvoiceId !== '') {
                $originalInvoice = SupplierInvoice::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($originalInvoiceId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $originalInvoice->vendor_id !== (int) $vendor->id) {
                    throw new ConflictHttpException('Credit memo vendor must match the original invoice vendor.');
                }

                if ($currency !== (string) $originalInvoice->currency) {
                    throw new ConflictHttpException('Credit memo currency must match the original invoice currency.');
                }
            }

            $lineSubtotalSum = '0.0000';
            foreach ($lines as $linePayload) {
                $quantity = (string) ($linePayload['quantity'] ?? '1');
                $unitPrice = (string) ($linePayload['unit_price'] ?? '0');
                $lineSubtotalSum = bcadd($lineSubtotalSum, bcmul($quantity, $unitPrice, 4), 4);
            }

            if (bccomp($lineSubtotalSum, $subtotal, 4) !== 0) {
                throw ValidationException::withMessages([
                    'subtotal' => sprintf(
                        'Math error: line subtotals sum %s does not match header subtotal %s.',
                        $lineSubtotalSum,
                        $subtotal,
                    ),
                ]);
            }

            $computedTotal = bcadd(bcadd($subtotal, $tax, 4), $freight, 4);
            if (bccomp($computedTotal, $total, 4) !== 0) {
                throw ValidationException::withMessages([
                    'total' => sprintf(
                        'Math error: subtotal + tax + freight %s does not match total %s.',
                        $computedTotal,
                        $total,
                    ),
                ]);
            }

            $number = $this->numberGenerator->generate((int) $tenant->id);

            $creditMemo = SupplierCreditMemo::query()->create([
                'tenant_id' => $tenant->id,
                'number' => $number,
                'vendor_credit_memo_number' => $vendorCreditMemoNumber !== '' ? $vendorCreditMemoNumber : null,
                'vendor_id' => $vendor->id,
                'original_invoice_id' => $originalInvoice?->id,
                'status' => SupplierCreditMemoStatus::Draft,
                'currency' => $currency,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'freight_amount' => $freight,
                'total_amount' => $total,
                'credit_date' => $creditDate,
                'notes' => $notes,
                'captured_by_user_id' => $actor->id,
                'captured_at' => now(),
                'lock_version' => 1,
            ]);

            foreach ($lines as $linePayload) {
                $quantity = (string) ($linePayload['quantity'] ?? '1');
                $unitPrice = (string) ($linePayload['unit_price'] ?? '0');
                $lineSubtotal = bcmul($quantity, $unitPrice, 4);

                SupplierCreditMemoLine::query()->create([
                    'tenant_id' => $tenant->id,
                    'supplier_credit_memo_id' => $creditMemo->id,
                    'purchase_order_line_id' => $linePayload['purchase_order_line_id'] ?? null,
                    'original_invoice_line_id' => $linePayload['original_invoice_line_id'] ?? null,
                    'line_number' => (int) $linePayload['line_number'],
                    'description_snapshot' => (string) $linePayload['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'tax_code' => $linePayload['tax_code'] ?? null,
                    'tax_amount' => (string) ($linePayload['tax_amount'] ?? '0'),
                    'notes' => $linePayload['notes'] ?? null,
                ]);
            }

            $creditMemo->load('lines');

            if ($originalInvoice !== null) {
                $taxMismatches = $this->taxMirrorValidator->validate(
                    (string) $tenant->id,
                    (string) $originalInvoice->id,
                    $lines,
                );

                foreach ($taxMismatches as $mismatch) {
                    SupplierCreditMemoException::query()->create([
                        'tenant_id' => $tenant->id,
                        'supplier_credit_memo_id' => $creditMemo->id,
                        'exception_type' => SupplierCreditMemoExceptionType::TaxCodeMismatch->value,
                        'severity' => SupplierCreditMemoExceptionSeverity::Warning->value,
                        'description' => sprintf(
                            'Line %s tax code %s does not mirror original invoice line tax code %s.',
                            $mismatch['line_number'],
                            $mismatch['credit_tax_code'] ?? 'null',
                            $mismatch['original_tax_code'] ?? 'null',
                        ),
                        'lock_version' => 1,
                    ]);
                }
            }

            if ($originalInvoice !== null && $vendorCreditMemoNumber !== '') {
                if ($this->duplicateDetector->isDuplicate(
                    (int) $tenant->id,
                    (int) $vendor->id,
                    (string) $originalInvoice->id,
                    $vendorCreditMemoNumber,
                    (string) $creditMemo->id,
                )) {
                    SupplierCreditMemoException::query()->create([
                        'tenant_id' => $tenant->id,
                        'supplier_credit_memo_id' => $creditMemo->id,
                        'exception_type' => SupplierCreditMemoExceptionType::DuplicateCredit->value,
                        'severity' => SupplierCreditMemoExceptionSeverity::Warning->value,
                        'description' => 'A credit memo with the same vendor, original invoice, and vendor credit memo number already exists.',
                        'lock_version' => 1,
                    ]);
                }
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'supplier_credit_memo.created',
                subject: $creditMemo,
                metadata: [
                    'number' => $number,
                    'vendorId' => (string) $vendor->id,
                    'originalInvoiceId' => $originalInvoice?->id !== null ? (string) $originalInvoice->id : null,
                    'totalAmount' => $total,
                    'currency' => $currency,
                    'lineCount' => count($lines),
                ],
            ));

            return $creditMemo->fresh(['lines', 'exceptions']);
        });
    }
}
