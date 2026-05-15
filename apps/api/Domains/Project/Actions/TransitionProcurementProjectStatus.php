<?php

namespace Domains\Project\Actions;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Project\States\ProjectStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TransitionProcurementProjectStatus
{
    public function handle(Tenant $tenant, User $actor, ProcurementProject $project, ProjectStatus $next, ?string $reason = null): ProcurementProject
    {
        if (! $project->status->canTransitionTo($next)) {
            throw new HttpException(409, 'Invalid project status transition.');
        }

        if ($next === ProjectStatus::Cancelled && blank($reason)) {
            throw new HttpException(422, 'Cancellation reason is required.');
        }

        $project->status = $next;

        if ($next === ProjectStatus::Cancelled) {
            $project->cancelled_at = now();
            $project->cancelled_by_id = $actor->id;
            $project->cancellation_reason = $reason;
        }

        if ($next === ProjectStatus::Completed) {
            $project->completed_at = now();
            $project->completed_by_id = $actor->id;
        }

        $project->save();

        $event = match ($next) {
            ProjectStatus::Active => 'project.activated',
            ProjectStatus::OnHold => 'project.on_hold',
            ProjectStatus::Completed => 'project.completed',
            ProjectStatus::Cancelled => 'project.cancelled',
            default => 'project.updated',
        };

        AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => $event,
            'action' => $event,
            'subject_type' => ProcurementProject::class,
            'subject_id' => $project->id,
            'metadata' => [
                'name' => $project->name,
                'number' => $project->number,
                'status' => $project->status->value,
                'reason' => $reason,
            ],
            'occurred_at' => now(),
        ]);

        return $project->load(['owner', 'requisitions', 'cancelledBy', 'completedBy']);
    }
}
