<?php

namespace Domains\Collaboration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Collaboration\Actions\CreateCollaborationComment;
use Domains\Collaboration\Http\Requests\CreateCollaborationCommentRequest;
use Domains\Collaboration\Http\Resources\CollaborationCommentResource;
use Domains\Collaboration\Models\CollaborationComment;
use Illuminate\Http\JsonResponse;

class ApprovalTaskCommentController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $approvalTask)
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $task = $this->findTenantTask($tenant, $approvalTask);

        $this->authorize('view', $task);

        $comments = CollaborationComment::query()
            ->with(['author', 'mentions.mentionedUser'])
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', ApprovalTask::class)
            ->where('subject_id', $task->id)
            ->oldest('created_at')
            ->get();

        return CollaborationCommentResource::collection($comments);
    }

    public function store(
        CreateCollaborationCommentRequest $request,
        CurrentTenant $currentTenant,
        CreateCollaborationComment $createCollaborationComment,
        int $approvalTask,
    ): JsonResponse|CollaborationCommentResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $task = $this->findTenantTask($tenant, $approvalTask);

        $this->authorize('view', $task);

        $comment = $createCollaborationComment->handle(
            $tenant,
            $request->user(),
            $task,
            $request->validated(),
        );

        return (new CollaborationCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    private function findTenantTask(Tenant $tenant, int $id): ApprovalTask
    {
        return ApprovalTask::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
