<?php

namespace Domains\Collaboration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Collaboration\Actions\CreateCollaborationComment;
use Domains\Collaboration\Http\Requests\CreateCollaborationCommentRequest;
use Domains\Collaboration\Http\Resources\CollaborationCommentResource;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RequisitionCommentController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $requisition)
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('view', $requisition);

        $comments = CollaborationComment::query()
            ->with(['author', 'mentions.mentionedUser'])
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', $requisition->getMorphClass())
            ->where('subject_id', $requisition->id)
            ->oldest('created_at')
            ->get();

        return CollaborationCommentResource::collection($comments);
    }

    public function store(
        CreateCollaborationCommentRequest $request,
        CurrentTenant $currentTenant,
        CreateCollaborationComment $createCollaborationComment,
        int $requisition,
    ): JsonResponse|CollaborationCommentResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('comment', $requisition);

        $comment = $createCollaborationComment->handle(
            $this->tenantOrAbort($currentTenant),
            $request->user(),
            $requisition,
            $request->validated(),
        );

        return (new CollaborationCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    public function mentionCandidates(Request $request, CurrentTenant $currentTenant, int $requisition)
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);
        $search = trim((string) $request->query('search', ''));

        $this->authorize('mention', $requisition);

        $users = $tenant->users()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->filter(fn ($user) => Gate::forUser($user)->allows('view', $requisition))
            ->values()
            ->map(fn ($user): array => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();

        return response()->json(['data' => $users]);
    }

    private function findTenantRequisition(CurrentTenant $currentTenant, int $id): Requisition
    {
        $tenant = $this->tenantOrAbort($currentTenant);

        return Requisition::query()
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
