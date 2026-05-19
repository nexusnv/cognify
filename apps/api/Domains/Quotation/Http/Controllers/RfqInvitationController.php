<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\CancelRfqInvitation;
use Domains\Quotation\Actions\CreateRfqInvitations;
use Domains\Quotation\Actions\ResendRfqInvitation;
use Domains\Quotation\Actions\UpdateRfqInvitationStatus;
use Domains\Quotation\Http\Requests\CancelRfqInvitationRequest;
use Domains\Quotation\Http\Requests\CreateRfqInvitationsRequest;
use Domains\Quotation\Http\Requests\UpdateRfqInvitationStatusRequest;
use Domains\Quotation\Http\Resources\RfqInvitationResource;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RfqInvitationController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $rfq): AnonymousResourceCollection
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('viewAny', [RfqInvitation::class, $model]);

        return RfqInvitationResource::collection(
            RfqInvitation::query()
                ->with('vendor')
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $model->id)
                ->latest()
                ->get()
        );
    }

    public function store(CreateRfqInvitationsRequest $request, CurrentTenant $currentTenant, int $rfq, CreateRfqInvitations $action): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);

        $invitations = $action->handle($tenant, $request->user(), $model, $request->validated());

        return RfqInvitationResource::collection($invitations)->response()->setStatusCode(201);
    }

    public function resend(Request $request, CurrentTenant $currentTenant, int $invitation, ResendRfqInvitation $action): RfqInvitationResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantInvitation($tenant, $invitation);

        return new RfqInvitationResource($action->handle($tenant, $request->user(), $model));
    }

    public function cancel(CancelRfqInvitationRequest $request, CurrentTenant $currentTenant, int $invitation, CancelRfqInvitation $action): RfqInvitationResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantInvitation($tenant, $invitation);

        return new RfqInvitationResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    public function status(UpdateRfqInvitationStatusRequest $request, CurrentTenant $currentTenant, int $invitation, UpdateRfqInvitationStatus $action): RfqInvitationResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantInvitation($tenant, $invitation);

        return new RfqInvitationResource($action->handle($tenant, $request->user(), $model, $request->validated()));
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->with(['sourcingIntakeReview.assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantInvitation(Tenant $tenant, int $id): RfqInvitation
    {
        return RfqInvitation::query()
            ->with('vendor')
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
