<?php

namespace Domains\PurchaseOrder\Http\Resources;

use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin PurchaseOrderRequestHandoff
 */
class PurchaseOrderRequestHandoffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrderRequestHandoff $handoff */
        $handoff = $this->resource;
        $user = $request->user();

        return [
            'id' => (string) $handoff->id,
            'number' => $handoff->number,
            'status' => $handoff->statusState()->value,
            'rfqId' => (string) $handoff->rfq_id,
            'recommendationId' => (string) $handoff->rfq_award_recommendation_id,
            'vendorId' => (string) $handoff->vendor_id,
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
            'readinessWarnings' => $handoff->readiness_warnings ?? [],
            'readyByUserId' => $handoff->ready_by_user_id !== null ? (string) $handoff->ready_by_user_id : null,
            'readyAt' => $handoff->ready_at?->toISOString(),
            'cancelledReason' => $handoff->cancelled_reason,
            'lastExportFormat' => $handoff->last_export_format,
            'lastExportedAt' => $handoff->last_exported_at?->toISOString(),
            'lockVersion' => $handoff->lock_version,
            'permissions' => [
                'canUpdate' => $user !== null && Gate::forUser($user)->check('update', $handoff),
                'canMarkReady' => $user !== null && Gate::forUser($user)->check('markReady', $handoff),
                'canExport' => $user !== null && Gate::forUser($user)->check('export', $handoff),
                'canCancel' => $user !== null && Gate::forUser($user)->check('cancel', $handoff),
            ],
        ];
    }
}
