<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderRequestHandoffNumber;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrRevealPurchaseOrderRequestHandoff
{
    public function __construct(
        private readonly BuildPurchaseOrderRequestHandoffSnapshot $snapshotBuilder,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(RfqAwardRecommendation $recommendation, User $actor): PurchaseOrderRequestHandoff
    {
        return DB::transaction(function () use ($recommendation, $actor): PurchaseOrderRequestHandoff {
            $recommendation = RfqAwardRecommendation::query()
                ->where('tenant_id', $recommendation->tenant_id)
                ->whereKey($recommendation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($recommendation->statusState() !== RfqAwardRecommendationStatus::Approved) {
                throw new ConflictHttpException('Only approved award recommendations can create PO handoffs.');
            }

            $existing = PurchaseOrderRequestHandoff::query()
                ->where('tenant_id', $recommendation->tenant_id)
                ->where('rfq_award_recommendation_id', $recommendation->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $snapshot = $this->snapshotBuilder->handle($recommendation);
            $recommendation->loadMissing(['tenant', 'rfq', 'recommendedVendor', 'recommendedQuotation', 'recommendedQuotationVersion']);
            $version = $recommendation->recommendedQuotationVersion;

            if (
                $recommendation->recommended_vendor_id === null
                || $recommendation->recommended_quotation_id === null
                || $recommendation->recommended_quotation_version_id === null
                || $version === null
                || $version->currency === null
                || $version->total_amount === null
            ) {
                throw new ConflictHttpException('Approved award recommendation is missing required PO handoff source details.');
            }

            $handoff = PurchaseOrderRequestHandoff::query()->create([
                'tenant_id' => $recommendation->tenant_id,
                'rfq_award_recommendation_id' => $recommendation->id,
                'approval_instance_id' => $recommendation->approval_instance_id,
                'rfq_id' => $recommendation->rfq_id,
                'requisition_id' => $recommendation->rfq?->requisition_id,
                'project_id' => $recommendation->rfq?->project_id,
                'vendor_id' => $recommendation->recommended_vendor_id,
                'quotation_id' => $recommendation->recommended_quotation_id,
                'quotation_version_id' => $recommendation->recommended_quotation_version_id,
                'number' => PurchaseOrderRequestHandoffNumber::next($recommendation->tenant),
                'status' => PurchaseOrderRequestHandoffStatus::Draft,
                'currency' => $version->currency,
                'subtotal_amount' => $version->subtotal_amount,
                'tax_amount' => $version->tax_amount,
                'freight_amount' => $version->freight_amount,
                'discount_amount' => $version->discount_amount,
                'total_amount' => $version->total_amount,
                'requested_by_user_id' => $actor->id,
                'source_snapshot' => $snapshot['source'],
                'line_snapshot' => $snapshot['lines'],
                'approval_snapshot' => $snapshot['approval'],
                'evidence_snapshot' => $snapshot['evidence'],
                'readiness_warnings' => $snapshot['warnings'],
                'lock_version' => 1,
            ]);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'purchase_order_handoff.created',
                subject: $handoff,
                metadata: ['recommendationId' => (string) $recommendation->id],
                after: $handoff->fresh()->toArray(),
            ));

            return $handoff->fresh();
        });
    }
}
