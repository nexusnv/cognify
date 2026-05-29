<?php

namespace Domains\Project\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Project\Actions\LinkRequisitionToProject;
use Domains\Project\Actions\UnlinkRequisitionFromProject;
use Domains\Project\Http\Requests\LinkProjectRequisitionRequest;
use Domains\Project\Http\Resources\ProjectRequisitionResource;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRequisitionController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant, int $project): JsonResponse
    {
        $projectModel = $this->findProject($currentTenant, $project);
        $this->authorize('view', $projectModel);

        $rows = Requisition::query()
            ->with(['requester', 'lineItems'])
            ->where('tenant_id', $projectModel->tenant_id)
            ->where('project_id', $projectModel->id)
            ->visibleTo($request->user(), $currentTenant->roleFor($request->user()), $projectModel->tenant_id)
            ->latest('updated_at')
            ->get();

        return response()->json(['data' => ProjectRequisitionResource::collection($rows)->resolve()]);
    }

    public function store(LinkProjectRequisitionRequest $request, CurrentTenant $currentTenant, LinkRequisitionToProject $action, int $project): JsonResponse
    {
        $projectModel = $this->findProject($currentTenant, $project);
        $this->authorize('linkRequisitions', $projectModel);

        $linked = $action->handle($currentTenant->get(), $request->user(), $projectModel, (int) $request->validated('requisitionId'));

        return (new ProjectRequisitionResource($linked))->response()->setStatusCode(201);
    }

    public function destroy(CurrentTenant $currentTenant, UnlinkRequisitionFromProject $action, int $project, int $requisition): ProjectRequisitionResource
    {
        $projectModel = $this->findProject($currentTenant, $project);
        $this->authorize('unlinkRequisitions', $projectModel);

        return new ProjectRequisitionResource($action->handle($currentTenant->get(), request()->user(), $projectModel, $requisition));
    }

    private function findProject(CurrentTenant $currentTenant, int $id): ProcurementProject
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return ProcurementProject::query()->where('tenant_id', $tenant->id)->findOrFail($id);
    }
}
