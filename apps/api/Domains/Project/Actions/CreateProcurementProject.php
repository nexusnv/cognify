<?php

namespace Domains\Project\Actions;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Project\Services\ProcurementProjectNumberGenerator;
use Domains\Project\States\ProjectStatus;
use Illuminate\Support\Facades\DB;

class CreateProcurementProject
{
    public function __construct(private readonly ProcurementProjectNumberGenerator $numberGenerator)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, array $data): ProcurementProject
    {
        return DB::transaction(function () use ($tenant, $actor, $data): ProcurementProject {
            $project = ProcurementProject::query()->create([
                'tenant_id' => $tenant->id,
                'owner_id' => (int) $data['ownerId'],
                'number' => $this->numberGenerator->nextForTenant($tenant),
                'name' => $data['name'],
                'charter' => $data['charter'] ?? null,
                'status' => ProjectStatus::Draft,
                'budget_amount' => $data['budgetAmount'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? 'MYR')),
                'department' => $data['department'] ?? null,
                'cost_center' => $data['costCenter'] ?? null,
                'target_start_date' => $data['targetStartDate'] ?? null,
                'target_completion_date' => $data['targetCompletionDate'] ?? null,
            ]);

            AuditEvent::query()->create([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'event_type' => 'project.created',
                'action' => 'project.created',
                'subject_type' => ProcurementProject::class,
                'subject_id' => $project->id,
                'metadata' => ['name' => $project->name, 'number' => $project->number],
                'occurred_at' => now(),
            ]);

            return $project->load(['owner', 'requisitions', 'cancelledBy', 'completedBy']);
        });
    }
}
