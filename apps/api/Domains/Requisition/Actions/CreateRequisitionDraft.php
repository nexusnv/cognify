<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Actions\AuthorizesRequisitionProjectLinking;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Services\RequisitionNumberGenerator;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;

class CreateRequisitionDraft
{
    use AuthorizesRequisitionProjectLinking;

    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RequisitionNumberGenerator $numberGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, array $data): Requisition
    {
        return DB::transaction(function () use ($tenant, $actor, $data): Requisition {
            $projectId = $data['projectId'] ?? null;
            if ($projectId !== null && $projectId !== '') {
                $this->findVisibleLinkableProject($tenant, $actor, $projectId);
            }

            $requisition = Requisition::query()->create([
                'tenant_id' => $tenant->id,
                'requester_id' => $actor->id,
                'number' => $this->numberGenerator->nextFor($tenant),
                'title' => $data['title'],
                'business_justification' => $data['businessJustification'] ?? null,
                'needed_by_date' => $data['neededByDate'] ?? null,
                'department' => $data['department'] ?? null,
                'project_id' => $projectId ?: null,
                'cost_center' => $data['costCenter'] ?? null,
                'delivery_location' => $data['deliveryLocation'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'MYR'),
                'status' => RequisitionStatus::Draft,
                'lock_version' => 0,
            ]);

            $this->replaceLineItems($requisition, $data['lineItems'] ?? []);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.created',
                subject: $requisition,
                metadata: [],
                after: [
                    'status' => RequisitionStatus::Draft->value,
                    'title' => $requisition->title,
                    'lineItemCount' => count($data['lineItems'] ?? []),
                ],
                subjectDisplay: $requisition->number,
            ));

            return $requisition->load(['requester', 'lineItems', 'project.owner']);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $lineItems
     */
    private function replaceLineItems(Requisition $requisition, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $requisition->lineItems()->create([
                'name' => $lineItem['name'],
                'description' => $lineItem['description'] ?? null,
                'quantity' => $lineItem['quantity'],
                'unit_of_measure' => $lineItem['unit'],
                'estimated_unit_price' => $lineItem['estimatedUnitPrice'],
                'currency' => strtoupper($lineItem['currency']),
            ]);
        }
    }
}
