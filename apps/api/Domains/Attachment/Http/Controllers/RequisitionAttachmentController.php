<?php

namespace Domains\Attachment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Attachment\Actions\StoreRequisitionAttachment;
use Domains\Attachment\Http\Requests\StoreAttachmentRequest;
use Domains\Attachment\Http\Resources\AttachmentResource;
use Domains\Attachment\Models\Attachment;
use Domains\Requisition\Models\Requisition;

class RequisitionAttachmentController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $requisition)
    {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('view', $requisition);

        $attachments = Attachment::query()
            ->with(['uploader', 'attachable'])
            ->where('tenant_id', $currentTenant->get()->id)
            ->where('attachable_type', Requisition::class)
            ->where('attachable_id', $requisition->id)
            ->latest('created_at')
            ->get();

        return AttachmentResource::collection($attachments);
    }

    public function store(
        StoreAttachmentRequest $request,
        CurrentTenant $currentTenant,
        StoreRequisitionAttachment $storeAttachment,
        int $requisition,
    ) {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $attachment = $storeAttachment->handle(
            $currentTenant->get(),
            $request->user(),
            $requisition,
            $request->file('file'),
        );

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    private function findTenantRequisition(CurrentTenant $currentTenant, int $id): Requisition
    {
        return Requisition::query()
            ->where('tenant_id', $currentTenant->get()->id)
            ->findOrFail($id);
    }
}
