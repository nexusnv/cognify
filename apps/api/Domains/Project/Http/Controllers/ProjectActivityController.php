<?php

namespace Domains\Project\Http\Controllers;

use App\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Project\Models\ProcurementProject;
use Illuminate\Http\JsonResponse;

class ProjectActivityController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $project): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        $projectRecord = ProcurementProject::query()->where('tenant_id', $tenant->id)->findOrFail($project);
        $this->authorize('view', $projectRecord);

        $events = AuditEvent::query()
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', ProcurementProject::class)
            ->where('subject_id', $projectRecord->id)
            ->latest('occurred_at')
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $events->map(fn (AuditEvent $event): array => [
                'id' => (string) ($event->event_id ?? $event->id),
                'type' => $event->action ?? $event->event_type,
                'actor' => $event->actor ? [
                    'id' => (string) $event->actor->id,
                    'name' => $event->actor->name,
                    'email' => $event->actor->email,
                ] : null,
                'metadata' => $event->metadata ?? [],
                'occurredAt' => $event->occurred_at?->toISOString(),
            ])->values()->all(),
        ]);
    }
}
