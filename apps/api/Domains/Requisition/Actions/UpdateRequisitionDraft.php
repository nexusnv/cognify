<?php

namespace Domains\Requisition\Actions;

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

            $this->auditRecorder->record($tenant, $actor, 'requisition.updated', $requisition, [
                'status' => $requisition->status->value,
            ]);

            return $requisition->refresh()->load(['requester', 'lineItems']);
        });
    }
}
