<?php

namespace Domains\Project\Actions;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LinkRequisitionToProject
{
    use AuthorizesRequisitionProjectLinking;

    public function handle(Tenant $tenant, User $actor, ProcurementProject $project, int $requisitionId): Requisition
    {
        if ($project->status->isTerminal()) {
            throw new HttpException(409, 'Terminal projects cannot be linked to requisitions.');
        }

        $requisition = Requisition::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($requisitionId)
            ->firstOrFail();

        if ($this->canLinkOrUnlinkRequisition($tenant, $actor, $requisition) === false) {
            throw new HttpException(403, 'You are not allowed to link this requisition to the project.');
        }

        if (in_array($requisition->status, [RequisitionStatus::Withdrawn, RequisitionStatus::Cancelled], true)) {
            throw new HttpException(409, 'Terminal requisitions cannot be linked to projects.');
        }

        if ($requisition->project_id !== null && (int) $requisition->project_id !== (int) $project->id) {
            throw new HttpException(409, 'Requisition is linked to another project; unlink first.');
        }

        $requisition->forceFill(['project_id' => $project->id])->save();

        AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => 'project.requisition_linked',
            'action' => 'project.requisition_linked',
            'subject_type' => ProcurementProject::class,
            'subject_id' => $project->id,
            'metadata' => ['requisitionId' => $requisition->id],
            'occurred_at' => now(),
        ]);

        AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => 'requisition.project_linked',
            'action' => 'requisition.project_linked',
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
            'metadata' => ['projectId' => $project->id],
            'occurred_at' => now(),
        ]);

        return $requisition->load(['requester', 'lineItems']);
    }
}
