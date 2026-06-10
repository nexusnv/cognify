<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ExportIssuedPurchaseOrder
{
    private const CSV_HEADERS = [
        'po_number',
        'supplier_version_number',
        'issued_at',
        'issue_method',
        'vendor_name',
        'currency',
        'line_number',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'line_total',
        'payment_terms',
        'delivery_terms',
    ];

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @return array<string, mixed>|string
     */
    public function handle(PurchaseOrder $purchaseOrder, User $actor, string $format, bool $recordExport = true): array|string
    {
        if (! $recordExport) {
            $this->assertExportable($purchaseOrder, $format);

            return $format === 'json' ? $this->jsonPayload($purchaseOrder) : $this->csvPayload($purchaseOrder);
        }

        return DB::transaction(function () use ($purchaseOrder, $actor, $format): array|string {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertExportable($purchaseOrder, $format);
            $before = $purchaseOrder->only(['last_supplier_exported_by_user_id', 'last_supplier_exported_at', 'last_supplier_export_format', 'lock_version']);
            $payload = $format === 'json' ? $this->jsonPayload($purchaseOrder) : $this->csvPayload($purchaseOrder);

            $purchaseOrder->forceFill([
                'last_supplier_exported_by_user_id' => $actor->id,
                'last_supplier_exported_at' => now(),
                'last_supplier_export_format' => $format,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.supplier_exported',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'format' => $format,
                    'supplierVersionNumber' => $purchaseOrder->supplier_version_number,
                ]),
                before: $before,
                after: $purchaseOrder->only(['last_supplier_exported_by_user_id', 'last_supplier_exported_at', 'last_supplier_export_format', 'lock_version']),
            ));

            return $payload;
        });
    }

    private function assertExportable(PurchaseOrder $purchaseOrder, string $format): void
    {
        if (! in_array($format, ['json', 'csv'], true)) {
            throw new InvalidArgumentException('Purchase order supplier export format must be json or csv.');
        }

        if (! in_array($purchaseOrder->statusState(), [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true)) {
            throw new ConflictHttpException('Only issued or acknowledged purchase orders can be exported to suppliers.');
        }

        if (! is_array($purchaseOrder->supplier_version) || $purchaseOrder->supplier_version === []) {
            throw new ConflictHttpException('Purchase order supplier version is not available.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(PurchaseOrder $purchaseOrder): array
    {
        $version = $purchaseOrder->supplier_version ?? [];

        return [
            'format' => 'json',
            'exportedAt' => now()->toISOString(),
            'purchaseOrder' => $version['purchaseOrder'] ?? [],
            'vendor' => $version['vendor'] ?? [],
            'lines' => $version['lines'] ?? [],
            'source' => $version['source'] ?? [],
            'approval' => $version['approval'] ?? [],
            'issue' => Arr::except($version, ['purchaseOrder', 'vendor', 'lines', 'source', 'approval']),
        ];
    }

    private function csvPayload(PurchaseOrder $purchaseOrder): string
    {
        $version = $purchaseOrder->supplier_version ?? [];
        $po = $version['purchaseOrder'] ?? [];
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new InvalidArgumentException('Unable to create purchase order supplier CSV export.');
        }

        fputcsv($stream, self::CSV_HEADERS);

        foreach (($version['lines'] ?? []) as $line) {
            fputcsv($stream, [
                $po['number'] ?? null,
                $version['versionNumber'] ?? null,
                $version['issuedAt'] ?? null,
                $version['issueMethod'] ?? null,
                data_get($version, 'vendor.name'),
                $po['currency'] ?? null,
                $line['lineNumber'] ?? null,
                $line['description'] ?? null,
                $line['quantity'] ?? null,
                $line['unit'] ?? null,
                $line['unitPrice'] ?? null,
                $line['lineTotal'] ?? null,
                $po['paymentTerms'] ?? null,
                $po['deliveryTerms'] ?? null,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
