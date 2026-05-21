<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\Tenant;
use Domains\Attachment\Http\Requests\StoreAttachmentRequest;
use Domains\Quotation\Actions\ResolveRfqInvitationPortalAccess;
use Domains\Quotation\Actions\SaveQuotationManualEntry;
use Domains\Quotation\Actions\StoreQuotationAttachment;
use Domains\Quotation\Http\Requests\SaveQuotationManualEntryRequest;
use Domains\Quotation\Http\Resources\QuotationResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorPortalQuotationController extends Controller
{
    public function show(string $token, ResolveRfqInvitationPortalAccess $resolve, Request $request): JsonResponse|QuotationResource
    {
        $request->attributes->set('vendor_portal', true);
        $invitation = $resolve->handle($token, $request);
        $request->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());
        $quotation = $this->findTenantQuotationByInvitation($invitation->tenant, $invitation->id);

        return $quotation === null
            ? response()->json(['data' => null])
            : new QuotationResource($quotation);
    }

    public function storeAttachment(
        string $token,
        StoreAttachmentRequest $request,
        ResolveRfqInvitationPortalAccess $resolve,
        StoreQuotationAttachment $storeQuotationAttachment,
    ): JsonResponse {
        $request->attributes->set('vendor_portal', true);
        request()->attributes->set('vendor_portal', true);
        $invitation = $resolve->handle($token, $request);
        $request->attributes->set('vendor_portal_can_edit_quotation', true);
        request()->attributes->set('vendor_portal_can_edit_quotation', true);

        $quotation = $storeQuotationAttachment->handle(
            $invitation->tenant,
            null,
            $invitation,
            $request->file('file'),
            QuotationSubmissionSource::VendorPortal,
        );

        return (new QuotationResource($quotation))->response()->setStatusCode(201);
    }

    public function saveManualEntry(
        string $token,
        SaveQuotationManualEntryRequest $request,
        ResolveRfqInvitationPortalAccess $resolve,
        SaveQuotationManualEntry $saveQuotationManualEntry,
    ): QuotationResource {
        $request->attributes->set('vendor_portal', true);
        $request->attributes->set('vendor_portal_can_edit_quotation', true);
        request()->attributes->set('vendor_portal', true);
        request()->attributes->set('vendor_portal_can_edit_quotation', true);
        $invitation = $resolve->handle($token, $request);

        return new QuotationResource($saveQuotationManualEntry->handle(
            $invitation->tenant,
            null,
            $invitation,
            $request->validated(),
            QuotationSubmissionSource::VendorPortal,
        ));
    }

    private function findTenantQuotationByInvitation(Tenant $tenant, int $invitationId): ?Quotation
    {
        return Quotation::query()
            ->with(['attachments' => fn ($query) => $query->with('uploader')->latest('created_at'), 'lineItems', 'submittedByUser', 'rfq', 'vendor', 'rfqInvitation', 'currentVersion.lineItems'])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_invitation_id', $invitationId)
            ->first();
    }
}
