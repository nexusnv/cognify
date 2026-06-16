<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Domains\Invoice\Services\InvoiceMatchingService;
use Domains\Invoice\Services\ToleranceService;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RunInvoiceMatching
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(SupplierInvoice $supplierInvoice, User $actor, int $lockVersion, string $triggerSource = 'manual'): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $triggerSource) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice->assertLockVersion($lockVersion);

            if ($invoice->statusState() !== SupplierInvoiceStatus::Reviewed) {
                throw new ConflictHttpException('Matching can only be run on reviewed invoices.');
            }

            $invoice->load(['lines', 'purchaseOrder']);

            // Lock PO lines for update to prevent concurrent modification
            $poLines = PurchaseOrderLine::query()
                ->whereIn('id', $invoice->purchaseOrder->lines->pluck('id'))
                ->where('tenant_id', $invoice->tenant_id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $toleranceService = new ToleranceService();
            $matchingService = new InvoiceMatchingService($toleranceService);

            $matchResult = $matchingService->match($invoice, $poLines);

            // Delete prior results for this invoice (supports re-run)
            SupplierInvoiceMatchResult::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->delete();

            // Persist new results
            $hasFailures = false;
            foreach ($matchResult['results'] as $result) {
                SupplierInvoiceMatchResult::create([
                    'tenant_id' => $invoice->tenant_id,
                    'supplier_invoice_id' => $invoice->id,
                    'supplier_invoice_line_id' => $result->supplierInvoiceLineId,
                    'purchase_order_line_id' => $result->purchaseOrderLineId,
                    'match_type' => $result->matchType,
                    'match_level' => $result->matchLevel,
                    'dimension' => $result->dimension,
                    'expected_value' => $result->expectedValue,
                    'actual_value' => $result->actualValue,
                    'tolerance_percent_applied' => $result->tolerancePercentApplied,
                    'tolerance_floor_applied' => $result->toleranceFloorApplied,
                    'tolerance_cap_applied' => $result->toleranceCapApplied,
                    'result' => $result->result,
                    'notes' => $result->notes,
                ]);

                if ($result->result === 'fail') {
                    $hasFailures = true;
                }
            }

            // Update cumulative invoiced on PO lines
            foreach ($matchResult['cumulativeInvoicedUpdates'] as $poLineId => $newCumulative) {
                PurchaseOrderLine::query()
                    ->whereKey($poLineId)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->lockForUpdate()
                    ->update(['cumulative_quantity_invoiced' => $newCumulative]);
            }

            // Set matching status
            $before = $invoice->only(['matching_status', 'lock_version']);
            $invoice->forceFill([
                'matching_status' => $hasFailures ? SupplierInvoiceStatus::Mismatch->value : SupplierInvoiceStatus::Matched->value,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();
            $after = $invoice->only(['matching_status', 'lock_version']);

            // Audit event
            $totalResults = count($matchResult['results']);
            $failResults = collect($matchResult['results'])->where('result', 'fail')->count();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.matching_completed',
                subject: $invoice,
                metadata: [
                    'matchingStatus' => $invoice->matching_status,
                    'totalResults' => $totalResults,
                    'failResults' => $failResults,
                    'triggerSource' => $triggerSource,
                    'matchingPolicy' => $invoice->purchaseOrder->matching_policy,
                    'dimensionsWithIssues' => collect($matchResult['results'])
                        ->where('result', 'fail')
                        ->pluck('dimension')
                        ->unique()
                        ->values()
                        ->toArray(),
                ],
                before: $before,
                after: $after,
            ));

            $invoice->refresh();

            return $invoice;
        });
    }
}
