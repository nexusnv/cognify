<?php

namespace Domains\Payments\Actions;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Payments\Data\PaymentImportPreviewData;
use Domains\Payments\Data\PaymentImportRowData;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Domains\Payments\Support\PaymentImportBatchIdGenerator;
use Domains\Payments\Support\PaymentImportCsvParser;
use Domains\Payments\Support\PaymentImportJsonParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ParsePaymentImportFile
{
    public function __construct(
        private readonly PaymentImportBatchIdGenerator $batchIdGenerator,
        private readonly PaymentImportCsvParser $csvParser,
        private readonly PaymentImportJsonParser $jsonParser,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function handle(UploadedFile $file, User $actor): PaymentImportPreviewData
    {
        $tenant = $this->currentTenant->get();
        if ($tenant === null) {
            throw new \RuntimeException('Tenant context missing.');
        }

        $batchId = $this->batchIdGenerator->generate();
        $extension = strtolower($file->getClientOriginalExtension());
        $content = (string) file_get_contents($file->getRealPath());

        $rows = match ($extension) {
            'csv' => $this->csvParser->parse($content),
            'json' => $this->jsonParser->parse($content),
            default => throw new \InvalidArgumentException('Import file must be CSV or JSON.'),
        };

        $parsedRows = DB::transaction(function () use ($rows, $tenant, $batchId, $actor): array {
            $parsedRows = [];
            foreach ($rows as $index => $rowData) {
                $parseError = $this->validateRow($rowData);

                $import = ApPaymentImport::query()->create([
                    'tenant_id' => $tenant->id,
                    'batch_id' => $batchId,
                    'row_index' => $index,
                    'handoff_number' => $rowData->handoffNumber,
                    'invoice_number' => $rowData->invoiceNumber,
                    'payment_reference' => $rowData->paymentReference,
                    'allocated_amount' => $rowData->allocatedAmount,
                    'mark_full' => $rowData->markFull,
                    'settlement_amount' => $rowData->settlementAmount,
                    'settlement_currency' => $rowData->settlementCurrency,
                    'paid_at' => $rowData->paidAt,
                    'settlement_method' => $rowData->settlementMethod,
                    'target_status' => $rowData->status,
                    'failure_code' => $rowData->failureCode,
                    'failure_reason' => $rowData->failureReason,
                    'void_reason' => $rowData->voidReason,
                    'status' => $parseError !== null ? ApPaymentImportStatus::Failed->value : ApPaymentImportStatus::Pending->value,
                    'match_error' => $parseError,
                    'imported_by_user_id' => $actor->id,
                    'imported_at' => now(),
                ]);

                $parsedRows[] = [
                    'id' => $import->id,
                    'rowIndex' => $index,
                    'handoffNumber' => $rowData->handoffNumber,
                    'invoiceNumber' => $rowData->invoiceNumber,
                    'targetStatus' => $rowData->status,
                    'status' => $import->status,
                    'matchError' => $parseError,
                ];
            }

            return $parsedRows;
        });

        return new PaymentImportPreviewData(
            batchId: $batchId,
            totalRows: count($rows),
            rows: $parsedRows,
        );
    }

    private function validateRow(PaymentImportRowData $row): ?string
    {
        if (empty($row->handoffNumber) && empty($row->invoiceNumber)) {
            return 'Either handoff_number or invoice_number is required.';
        }

        try {
            ApPaymentImportTargetStatus::from($row->status);
        } catch (\ValueError) {
            return "Invalid target_status: {$row->status}.";
        }

        if ($row->status === 'failed' && (empty($row->failureCode) || empty($row->failureReason))) {
            return 'failure_code and failure_reason are required when status is failed.';
        }

        if ($row->status === 'voided' && empty($row->voidReason)) {
            return 'void_reason is required when status is voided.';
        }

        if ($row->status === 'paid' && ! $row->markFull && $row->allocatedAmount === null) {
            return 'allocated_amount is required when status is paid and mark_full is false.';
        }

        return null;
    }
}
