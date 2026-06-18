<?php

namespace Domains\AccountsPayable\Http\Resources;

use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin ApPaymentHandoff
 */
class ApPaymentHandoffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'status' => $this->statusState()->value,
            'effectivePaymentDate' => $this->effective_payment_date?->toDateString(),
            'notes' => $this->notes,
            'currency' => $this->currency,
            'totalAmount' => (string) $this->total_amount,
            'remittanceReference' => $this->remittance_reference,
            'invoices' => $this->whenLoaded('invoices', fn() => $this->invoices->map(fn($inv) => [
                'id' => (string) $inv->id,
                'number' => $inv->number,
                'invoiceNumber' => $inv->invoice_number,
                'totalAmount' => (string) $inv->total_amount,
                'dueDate' => $inv->due_date?->toDateString(),
                'currency' => $inv->currency,
            ])),
            'snapshot' => $this->snapshot,
            'readinessWarnings' => $this->readiness_warnings,
            'createdBy' => $this->whenLoaded('createdByUser', fn() => [
                'id' => (string) $this->createdByUser?->id,
                'name' => $this->createdByUser?->name,
            ]),
            'readyBy' => $this->whenLoaded('readyByUser', fn() => [
                'id' => (string) $this->readyByUser?->id,
                'name' => $this->readyByUser?->name,
            ]),
            'readyAt' => $this->ready_at?->toISOString(),
            'cancelledBy' => $this->whenLoaded('cancelledByUser', fn() => [
                'id' => (string) $this->cancelledByUser?->id,
                'name' => $this->cancelledByUser?->name,
            ]),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelledReason' => $this->cancelled_reason,
            'lastExportedBy' => $this->whenLoaded('lastExportedByUser', fn() => [
                'id' => (string) $this->lastExportedByUser?->id,
                'name' => $this->lastExportedByUser?->name,
            ]),
            'lastExportedAt' => $this->last_exported_at?->toISOString(),
            'lastExportFormat' => $this->last_export_format,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $this->resource),
                'canMarkReady' => Gate::allows('markReady', $this->resource),
                'canCancel' => Gate::allows('cancel', $this->resource),
                'canExport' => Gate::allows('export', $this->resource),
            ],
        ];
    }
}
