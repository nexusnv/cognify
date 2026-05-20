<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Attachment\Http\Requests\StoreAttachmentRequest;
use Domains\Attachment\Http\Resources\AttachmentResource;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Actions\SaveQuotationManualEntry;
use Domains\Quotation\Actions\StoreQuotationAttachment;
use Domains\Quotation\Http\Requests\SaveQuotationManualEntryRequest;
use Domains\Quotation\Http\Resources\QuotationResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\RfqInvitationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RfqInvitationQuotationController extends Controller
{
    public function show(CurrentTenant $currentTenant, int $invitation): JsonResponse|QuotationResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantInvitation($tenant, $invitation);
        $this->authorize('view', $model->rfq);

        $quotation = $this->findTenantQuotationByInvitation($tenant, $model->id);

        return $quotation === null
            ? response()->json(['data' => null])
            : new QuotationResource($quotation);
    }

    public function storeAttachment(
        StoreAttachmentRequest $request,
        CurrentTenant $currentTenant,
        int $invitation,
        StoreQuotationAttachment $storeQuotationAttachment,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantInvitation($tenant, $invitation);
        $this->authorize('view', $model->rfq);
        $this->ensureInvitationAcceptsQuotation($model);

        $quotation = $storeQuotationAttachment->handle(
            $tenant,
            $request->user(),
            $model,
            $request->file('file'),
            QuotationSubmissionSource::BuyerUpload,
        );

        return (new QuotationResource($quotation))->response()->setStatusCode(201);
    }

    public function saveManualEntry(
        SaveQuotationManualEntryRequest $request,
        CurrentTenant $currentTenant,
        int $quotation,
        SaveQuotationManualEntry $saveQuotationManualEntry,
    ): QuotationResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $model->loadMissing(['rfqInvitation', 'rfq', 'vendor']);
        $this->authorize('view', $model->rfq);
        $this->ensureInvitationAcceptsQuotation($model->rfqInvitation);

        return new QuotationResource($saveQuotationManualEntry->handle(
            $tenant,
            $request->user(),
            $model->rfqInvitation,
            $request->validated(),
            QuotationSubmissionSource::BuyerUpload,
        ));
    }

    public function saveManualEntryForInvitation(
        SaveQuotationManualEntryRequest $request,
        CurrentTenant $currentTenant,
        int $invitation,
        SaveQuotationManualEntry $saveQuotationManualEntry,
    ): QuotationResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantInvitation($tenant, $invitation);
        $this->authorize('view', $model->rfq);
        $this->ensureInvitationAcceptsQuotation($model);

        return new QuotationResource($saveQuotationManualEntry->handle(
            $tenant,
            $request->user(),
            $model,
            $request->validated(),
            QuotationSubmissionSource::BuyerUpload,
        ));
    }

    public function attachments(CurrentTenant $currentTenant, int $quotation): AnonymousResourceCollection
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $this->authorize('view', $model->rfq);

        return AttachmentResource::collection(
            Attachment::query()
                ->with(['uploader', 'attachable'])
                ->where('tenant_id', $tenant->id)
                ->where('attachable_type', Quotation::class)
                ->where('attachable_id', $model->id)
                ->latest('created_at')
                ->get()
        );
    }

    private function findTenantInvitation(Tenant $tenant, int $id): RfqInvitation
    {
        return RfqInvitation::query()
            ->with(['rfq', 'vendor'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantQuotation(Tenant $tenant, int $id): Quotation
    {
        return Quotation::query()
            ->with(['rfq', 'vendor', 'rfqInvitation', 'lineItems'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantQuotationByInvitation(Tenant $tenant, int $invitationId): ?Quotation
    {
        return Quotation::query()
            ->with(['attachments' => fn ($query) => $query->with('uploader')->latest('created_at'), 'lineItems', 'submittedByUser', 'rfq', 'vendor', 'rfqInvitation'])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_invitation_id', $invitationId)
            ->first();
    }

    private function ensureInvitationAcceptsQuotation(RfqInvitation $invitation): void
    {
        if (in_array($invitation->status, [RfqInvitationStatus::Sent, RfqInvitationStatus::Acknowledged], true)) {
            return;
        }

        throw new ConflictHttpException('This RFQ invitation is not accepting quotation uploads.');
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
