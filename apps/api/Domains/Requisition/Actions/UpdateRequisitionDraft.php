<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateRequisitionDraft
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, Requisition $requisition, array $data): Requisition
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            throw new ConflictHttpException('Only draft requisitions can be updated.');
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition, $data): Requisition {
            $requisition = Requisition::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($requisition->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $data['lockVersion'] !== (int) $requisition->lock_version) {
                throw new ConflictHttpException('The draft has changed since it was loaded.');
            }

            $before = [
                'title' => $requisition->title,
                'businessJustification' => $requisition->business_justification,
                'neededByDate' => $requisition->needed_by_date?->toDateString(),
                'department' => $requisition->department,
                'projectId' => $requisition->project_id,
                'costCenter' => $requisition->cost_center,
                'deliveryLocation' => $requisition->delivery_location,
                'currency' => $requisition->currency,
                'status' => $requisition->status->value,
                'lockVersion' => $requisition->lock_version,
                'lineItemCount' => $requisition->lineItems()->count(),
            ];

            $requisition->fill([
                'title' => $data['title'] ?? $requisition->title,
                'business_justification' => array_key_exists('businessJustification', $data)
                    ? $data['businessJustification']
                    : $requisition->business_justification,
                'needed_by_date' => array_key_exists('neededByDate', $data)
                    ? $data['neededByDate']
                    : $requisition->needed_by_date,
                'department' => array_key_exists('department', $data) ? $data['department'] : $requisition->department,
                'project_id' => array_key_exists('projectId', $data) ? $data['projectId'] : $requisition->project_id,
                'cost_center' => array_key_exists('costCenter', $data) ? $data['costCenter'] : $requisition->cost_center,
                'delivery_location' => array_key_exists('deliveryLocation', $data)
                    ? $data['deliveryLocation']
                    : $requisition->delivery_location,
                'currency' => array_key_exists('currency', $data)
                    ? strtoupper($data['currency'] ?? 'MYR')
                    : $requisition->currency,
                'lock_version' => $requisition->lock_version + 1,
            ])->save();

            if (array_key_exists('lineItems', $data)) {
                $requisition->lineItems()->delete();

                foreach ($data['lineItems'] as $lineItem) {
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

            $afterLineItemCount = array_key_exists('lineItems', $data)
                ? count($data['lineItems'])
                : $before['lineItemCount'];

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.updated',
                subject: $requisition,
                metadata: [],
                before: $before,
                after: [
                    'title' => $requisition->title,
                    'businessJustification' => $requisition->business_justification,
                    'neededByDate' => $requisition->needed_by_date?->toDateString(),
                    'department' => $requisition->department,
                    'projectId' => $requisition->project_id,
                    'costCenter' => $requisition->cost_center,
                    'deliveryLocation' => $requisition->delivery_location,
                    'currency' => $requisition->currency,
                    'status' => $requisition->status->value,
                    'lockVersion' => $requisition->lock_version,
                    'lineItemCount' => $afterLineItemCount,
                ],
                subjectDisplay: $requisition->number,
            ));

            return $requisition->refresh()->load(['requester', 'lineItems']);
        });
    }
}
