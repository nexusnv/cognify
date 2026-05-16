<?php

namespace Domains\Project\Actions;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateProcurementProject
{
    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, ProcurementProject $project, array $data): ProcurementProject
    {
        if ($project->status->isTerminal()) {
            throw new HttpException(409, 'Terminal projects cannot be updated.');
        }

        $project->fill([
            'name' => $data['name'] ?? $project->name,
            'charter' => $data['charter'] ?? $project->charter,
            'owner_id' => array_key_exists('ownerId', $data) ? (int) $data['ownerId'] : $project->owner_id,
            'budget_amount' => $data['budgetAmount'] ?? $project->budget_amount,
            'currency' => array_key_exists('currency', $data) ? strtoupper((string) $data['currency']) : $project->currency,
            'department' => $data['department'] ?? $project->department,
            'cost_center' => $data['costCenter'] ?? $project->cost_center,
            'target_start_date' => $data['targetStartDate'] ?? $project->target_start_date,
            'target_completion_date' => $data['targetCompletionDate'] ?? $project->target_completion_date,
        ]);

        DB::transaction(function () use ($tenant, $actor, $project): void {
            $project->save();

            AuditEvent::query()->create([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'event_type' => 'project.updated',
                'action' => 'project.updated',
                'subject_type' => ProcurementProject::class,
                'subject_id' => $project->id,
                'metadata' => ['name' => $project->name, 'number' => $project->number],
                'occurred_at' => now(),
            ]);
        });

        return $project->load(['owner', 'requisitions', 'cancelledBy', 'completedBy']);
    }
}
