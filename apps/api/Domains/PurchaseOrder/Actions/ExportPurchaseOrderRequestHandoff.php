<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ExportPurchaseOrderRequestHandoff
{
    private const CSV_HEADERS = [
        'handoff_number',
        'handoff_status',
        'rfq_number',
        'requisition_number',
        'project_number',
        'vendor_name',
        'vendor_id',
        'quotation_number',
        'quotation_version',
        'currency',
        'po_total_amount',
        'line_number',
        'item_code',
        'description',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'line_total',
        'payment_terms',
        'delivery_terms',
        'warranty_terms',
        'lead_time_days',
        'approval_instance_id',
        'approved_at',
        'approved_by',
        'award_rationale',
        'finance_note',
        'export_memo',
    ];

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @return array<string, mixed>|string
     */
    public function handle(PurchaseOrderRequestHandoff $handoff, User $actor, string $format): array|string
    {
        return DB::transaction(function () use ($handoff, $actor, $format): array|string {
            $handoff = PurchaseOrderRequestHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($format, ['json', 'csv'], true)) {
                throw new InvalidArgumentException('PO handoff export format must be json or csv.');
            }

            if (! in_array($handoff->statusState(), [PurchaseOrderRequestHandoffStatus::Ready, PurchaseOrderRequestHandoffStatus::Exported], true)) {
                throw new ConflictHttpException('Only ready or exported PO handoffs can be exported.');
            }

            $before = $handoff->only(['status', 'last_exported_by_user_id', 'last_exported_at', 'last_export_format', 'lock_version']);
            $payload = $format === 'json' ? $this->jsonPayload($handoff) : $this->csvPayload($handoff);

            $handoff->forceFill([
                'status' => $handoff->statusState() === PurchaseOrderRequestHandoffStatus::Ready
                    ? PurchaseOrderRequestHandoffStatus::Exported
                    : $handoff->status,
                'last_exported_by_user_id' => $actor->id,
                'last_exported_at' => now(),
                'last_export_format' => $format,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'purchase_order_handoff.exported',
                subject: $handoff,
                metadata: ['format' => $format],
                before: $before,
                after: $handoff->only(['status', 'last_exported_by_user_id', 'last_exported_at', 'last_export_format', 'lock_version']),
            ));

            return $payload;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(PurchaseOrderRequestHandoff $handoff): array
    {
        return [
            'format' => 'json',
            'exportedAt' => now()->toISOString(),
            'handoff' => [
                'id' => (string) $handoff->id,
                'number' => $handoff->number,
                'status' => $handoff->statusState()->value,
                'currency' => $handoff->currency,
                'totalAmount' => $handoff->total_amount !== null ? (string) $handoff->total_amount : null,
                'source' => $handoff->source_snapshot ?? [],
                'lines' => $handoff->line_snapshot ?? [],
                'approval' => $handoff->approval_snapshot ?? [],
                'evidence' => $handoff->evidence_snapshot ?? [],
                'review' => [
                    'requestedPoDate' => $handoff->requested_po_date?->toDateString(),
                    'deliveryAttention' => $handoff->delivery_attention,
                    'financeNote' => $handoff->finance_note,
                    'exportMemo' => $handoff->export_memo,
                ],
            ],
        ];
    }

    private function csvPayload(PurchaseOrderRequestHandoff $handoff): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new InvalidArgumentException('Unable to create PO handoff CSV export.');
        }

        fputcsv($stream, self::CSV_HEADERS);

        foreach (($handoff->line_snapshot ?? []) as $line) {
            fputcsv($stream, [
                $handoff->number,
                $handoff->statusState()->value,
                data_get($handoff->source_snapshot, 'rfq.number'),
                data_get($handoff->source_snapshot, 'rfq.requisition.number'),
                data_get($handoff->source_snapshot, 'rfq.project.number'),
                data_get($handoff->source_snapshot, 'vendor.name'),
                (string) $handoff->vendor_id,
                data_get($handoff->source_snapshot, 'quotation.number'),
                data_get($handoff->source_snapshot, 'quotationVersion.versionNumber'),
                $handoff->currency,
                $handoff->total_amount !== null ? (string) $handoff->total_amount : null,
                data_get($line, 'lineNumber'),
                data_get($line, 'itemCode'),
                data_get($line, 'description'),
                data_get($line, 'quantity'),
                data_get($line, 'unitOfMeasure'),
                data_get($line, 'unitPrice'),
                data_get($line, 'taxAmount'),
                data_get($line, 'freightAmount'),
                data_get($line, 'discountAmount'),
                data_get($line, 'lineTotal'),
                data_get($handoff->source_snapshot, 'quotationVersion.paymentTerms') ?? data_get($handoff->source_snapshot, 'quotation.paymentTerms'),
                data_get($handoff->source_snapshot, 'quotationVersion.deliveryTerms') ?? data_get($handoff->source_snapshot, 'quotation.deliveryTerms'),
                data_get($handoff->source_snapshot, 'quotationVersion.warrantyTerms') ?? data_get($handoff->source_snapshot, 'quotation.warrantyTerms'),
                data_get($handoff->source_snapshot, 'quotationVersion.leadTimeDays') ?? data_get($handoff->source_snapshot, 'quotation.leadTimeDays'),
                data_get($handoff->approval_snapshot, 'approvalInstanceId'),
                data_get($handoff->approval_snapshot, 'approvedAt'),
                data_get($handoff->approval_snapshot, 'approvedBy'),
                data_get($handoff->source_snapshot, 'award.rationale'),
                $handoff->finance_note,
                $handoff->export_memo,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
