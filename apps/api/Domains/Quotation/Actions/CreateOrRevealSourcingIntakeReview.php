<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrRevealSourcingIntakeReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @return array{review:SourcingIntakeReview, created:bool}
     */
    public function handle(Tenant $tenant, User $actor, Requisition $requisition): array
    {
        if (! in_array($requisition->status, [RequisitionStatus::Submitted, RequisitionStatus::Approved], true)) {
            throw new ConflictHttpException('Only submitted or approved requisitions can enter sourcing intake.');
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition): array {
            $locked = Requisition::query()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->findOrFail($requisition->id);

            $review = SourcingIntakeReview::query()
                ->where('tenant_id', $tenant->id)
                ->where('requisition_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($review !== null) {
                return ['review' => $this->loadReview($review), 'created' => false];
            }

            $review = SourcingIntakeReview::query()->create([
                'tenant_id' => $tenant->id,
                'requisition_id' => $locked->id,
                'project_id' => $locked->project_id,
                'status' => SourcingIntakeStatus::Open,
                'checklist' => [],
            ]);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'sourcing_intake.created',
                subject: $review,
                metadata: ['requisitionId' => (string) $locked->id],
                subjectDisplay: $locked->number,
            ));

            return ['review' => $this->loadReview($review), 'created' => true];
        });
    }

    private function loadReview(SourcingIntakeReview $review): SourcingIntakeReview
    {
        return $review->refresh()->load(['assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }
}
