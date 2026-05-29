<?php

namespace Domains\Project\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Project\Actions\CreateProcurementProject;
use Domains\Project\Actions\TransitionProcurementProjectStatus;
use Domains\Project\Actions\UpdateProcurementProject;
use Domains\Project\Http\Requests\StoreProcurementProjectRequest;
use Domains\Project\Http\Requests\TransitionProcurementProjectRequest;
use Domains\Project\Http\Requests\UpdateProcurementProjectRequest;
use Domains\Project\Http\Resources\ProcurementProjectResource;
use Domains\Project\Models\ProcurementProject;
use Domains\Project\States\ProjectStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementProjectController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $this->authorize('viewAny', ProcurementProject::class);
        $tenant = $this->tenantOrAbort($currentTenant);
        $role = $currentTenant->roleFor($request->user());

        $query = ProcurementProject::query()
            ->with(['owner', 'cancelledBy', 'completedBy'])
            ->where('tenant_id', $tenant->id);

        $this->applyVisibilityScope($query, $request->user(), $role, $tenant->id);

        $query->when($request->query('search'), function ($query, string $search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('cost_center', 'like', "%{$search}%");
            });
        });
        $query->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status));
        $query->when($request->query('ownerId'), fn ($query, string $ownerId) => $query->where('owner_id', $ownerId));
        $query->when($request->query('department'), fn ($query, string $department) => $query->where('department', $department));
        $query->when($request->query('costCenter'), fn ($query, string $costCenter) => $query->where('cost_center', $costCenter));
        $query->when($request->query('updatedFrom'), fn ($query, string $date) => $query->whereDate('updated_at', '>=', $date));
        $query->when($request->query('updatedTo'), fn ($query, string $date) => $query->whereDate('updated_at', '<=', $date));

        $sort = $request->query('sort', 'updated_desc');

        if ($sort === 'updated_asc') {
            $query->orderBy('updated_at')->orderBy('id');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('name')->orderBy('id');
        } elseif ($sort === 'name_desc') {
            $query->orderByDesc('name')->orderByDesc('id');
        } else {
            $query->orderByDesc('updated_at')->orderByDesc('id');
        }

        $perPage = max(1, min($request->integer('perPage', 15), 100));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ProcurementProjectResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreProcurementProjectRequest $request, CurrentTenant $currentTenant, CreateProcurementProject $action): JsonResponse
    {
        $project = $action->handle($this->tenantOrAbort($currentTenant), $request->user(), $request->validated());

        return (new ProcurementProjectResource($project))->response()->setStatusCode(201);
    }

    public function show(Request $request, CurrentTenant $currentTenant, int $project): ProcurementProjectResource
    {
        $project = $this->findTenantProject($currentTenant, $project)->load(['owner', 'cancelledBy', 'completedBy']);
        $this->authorize('view', $project);

        return new ProcurementProjectResource($project);
    }

    public function update(UpdateProcurementProjectRequest $request, CurrentTenant $currentTenant, UpdateProcurementProject $action, int $project): ProcurementProjectResource
    {
        $project = $this->findTenantProject($currentTenant, $project);
        $this->authorize('update', $project);

        return new ProcurementProjectResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $project, $request->validated()));
    }

    public function activate(TransitionProcurementProjectRequest $request, CurrentTenant $currentTenant, TransitionProcurementProjectStatus $action, int $project): ProcurementProjectResource
    {
        return $this->transition($request, $currentTenant, $action, $project, ProjectStatus::Active);
    }

    public function hold(TransitionProcurementProjectRequest $request, CurrentTenant $currentTenant, TransitionProcurementProjectStatus $action, int $project): ProcurementProjectResource
    {
        return $this->transition($request, $currentTenant, $action, $project, ProjectStatus::OnHold);
    }

    public function resume(TransitionProcurementProjectRequest $request, CurrentTenant $currentTenant, TransitionProcurementProjectStatus $action, int $project): ProcurementProjectResource
    {
        return $this->transition($request, $currentTenant, $action, $project, ProjectStatus::Active);
    }

    public function complete(TransitionProcurementProjectRequest $request, CurrentTenant $currentTenant, TransitionProcurementProjectStatus $action, int $project): ProcurementProjectResource
    {
        return $this->transition($request, $currentTenant, $action, $project, ProjectStatus::Completed);
    }

    public function cancel(TransitionProcurementProjectRequest $request, CurrentTenant $currentTenant, TransitionProcurementProjectStatus $action, int $project): ProcurementProjectResource
    {
        return $this->transition($request, $currentTenant, $action, $project, ProjectStatus::Cancelled, $request->validated('reason'));
    }

    private function transition(TransitionProcurementProjectRequest $request, CurrentTenant $currentTenant, TransitionProcurementProjectStatus $action, int $id, ProjectStatus $next, ?string $reason = null): ProcurementProjectResource
    {
        $project = $this->findTenantProject($currentTenant, $id);
        $this->authorize($next === ProjectStatus::Cancelled ? 'cancel' : 'transition', $project);

        return new ProcurementProjectResource($action->handle($this->tenantOrAbort($currentTenant), $request->user(), $project, $next, $reason));
    }

    private function findTenantProject(CurrentTenant $currentTenant, int $id): ProcurementProject
    {
        return ProcurementProject::query()->where('tenant_id', $this->tenantOrAbort($currentTenant)->id)->findOrFail($id);
    }

    private function applyVisibilityScope($query, $user, ?string $role, int $tenantId): void
    {
        $query->visibleTo($user, $role, $tenantId);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
