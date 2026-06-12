<?php

namespace Domains\Attachment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Attachment\Actions\StoreSupplierInvoiceAttachment;
use Domains\Attachment\Http\Requests\StoreAttachmentRequest;
use Domains\Attachment\Http\Resources\AttachmentResource;
use Domains\Attachment\Models\Attachment;
use Domains\Invoice\Models\SupplierInvoice;

class SupplierInvoiceAttachmentController extends Controller
{
    public function index(CurrentTenant $currentTenant, string $supplierInvoice)
    {
        $supplierInvoice = $this->findTenantSupplierInvoice($currentTenant, $supplierInvoice);

        $this->authorize('view', $supplierInvoice);

        $attachments = Attachment::query()
            ->with(['uploader', 'attachable'])
            ->where('tenant_id', $currentTenant->get()->id)
            ->where('attachable_type', SupplierInvoice::class)
            ->where('attachable_id', $supplierInvoice->id)
            ->latest('created_at')
            ->get();

        return AttachmentResource::collection($attachments);
    }

    public function store(
        StoreAttachmentRequest $request,
        CurrentTenant $currentTenant,
        StoreSupplierInvoiceAttachment $storeAttachment,
        string $supplierInvoice,
    ) {
        $supplierInvoice = $this->findTenantSupplierInvoice($currentTenant, $supplierInvoice);

        $attachment = $storeAttachment->handle(
            $currentTenant->get(),
            $request->user(),
            $supplierInvoice,
            $request->file('file'),
        );

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    private function findTenantSupplierInvoice(CurrentTenant $currentTenant, string $id): SupplierInvoice
    {
        return SupplierInvoice::query()
            ->where('tenant_id', $currentTenant->get()->id)
            ->findOrFail($id);
    }
}
