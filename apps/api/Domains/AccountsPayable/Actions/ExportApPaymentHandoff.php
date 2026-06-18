<?php

namespace Domains\AccountsPayable\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ExportApPaymentHandoff
{
    private const CSV_HEADERS = [
        'handoff_number',
        'handoff_status',
        'effective_payment_date',
        'currency',
        'total_amount',
        'notes',
        'remittance_reference',
        'invoice_number',
        'invoice_invoice_number',
        'invoice_total_amount',
        'invoice_due_date',
        'invoice_currency',
        'vendor_id',
        'created_at',
    ];

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @return array<string, mixed>|string
     */
    public function handle(ApPaymentHandoff $handoff, User $actor, string $format, bool $recordExport = true): array|string
    {
        if (! $recordExport) {
            $this->assertExportable($handoff, $format);

            return $format === 'json' ? $this->jsonPayload($handoff) : $this->csvPayload($handoff);
        }

        return DB::transaction(function () use ($handoff, $actor, $format): array|string {
            $handoff = ApPaymentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertExportable($handoff, $format);

            $before = $handoff->only(['status', 'last_exported_by_user_id', 'last_exported_at', 'last_export_format', 'lock_version']);
            $payload = $format === 'json' ? $this->jsonPayload($handoff) : $this->csvPayload($handoff);

            $handoff->forceFill([
                'status' => $handoff->statusState() === ApPaymentHandoffStatus::Ready
                    ? ApPaymentHandoffStatus::Exported
                    : $handoff->status,
                'last_exported_by_user_id' => $actor->id,
                'last_exported_at' => now(),
                'last_export_format' => $format,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.exported',
                subject: $handoff,
                metadata: ['format' => $format],
                before: $before,
                after: $handoff->only(['status', 'last_exported_by_user_id', 'last_exported_at', 'last_export_format', 'lock_version']),
            ));

            return $payload;
        });
    }

    private function assertExportable(ApPaymentHandoff $handoff, string $format): void
    {
        if (! in_array($format, ['json', 'csv'], true)) {
            throw new InvalidArgumentException('AP payment handoff export format must be json or csv.');
        }

        if (! in_array($handoff->statusState(), [ApPaymentHandoffStatus::Ready, ApPaymentHandoffStatus::Exported], true)) {
            throw new ConflictHttpException('Only ready or exported AP payment handoffs can be exported.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(ApPaymentHandoff $handoff): array
    {
        $invoices = $handoff->invoices()->get();

        return [
            'format' => 'json',
            'exportedAt' => now()->toISOString(),
            'handoff' => [
                'id' => (string) $handoff->id,
                'number' => $handoff->number,
                'status' => $handoff->statusState()->value,
                'currency' => $handoff->currency,
                'totalAmount' => $handoff->total_amount !== null ? (string) $handoff->total_amount : null,
                'effectivePaymentDate' => $handoff->effective_payment_date?->toDateString(),
                'notes' => $handoff->notes,
                'remittanceReference' => $handoff->remittance_reference,
                'invoices' => $invoices->map(fn ($invoice) => [
                    'id' => (string) $invoice->id,
                    'number' => $invoice->number,
                    'invoiceNumber' => $invoice->invoice_number,
                    'totalAmount' => $invoice->total_amount !== null ? (string) $invoice->total_amount : null,
                    'dueDate' => $invoice->due_date?->toDateString(),
                    'currency' => $invoice->currency,
                ])->all(),
            ],
        ];
    }

    private function csvPayload(ApPaymentHandoff $handoff): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new InvalidArgumentException('Unable to create AP payment handoff CSV export.');
        }

        fputcsv($stream, self::CSV_HEADERS);

        $invoices = $handoff->invoices()->get();

        foreach ($invoices as $invoice) {
            fputcsv($stream, [
                $handoff->number,
                $handoff->statusState()->value,
                $handoff->effective_payment_date?->toDateString(),
                $handoff->currency,
                $handoff->total_amount !== null ? (string) $handoff->total_amount : null,
                $handoff->notes,
                $handoff->remittance_reference,
                $invoice->number,
                $invoice->invoice_number,
                $invoice->total_amount !== null ? (string) $invoice->total_amount : null,
                $invoice->due_date?->toDateString(),
                $invoice->currency,
                (string) $invoice->vendor_id,
                $handoff->created_at?->toISOString(),
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
