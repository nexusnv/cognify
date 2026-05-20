<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\Tenant;
use Domains\Attachment\Http\Requests\StoreAttachmentRequest;
use Domains\Quotation\Actions\ResolveRfqInvitationPortalAccess;
use Domains\Quotation\Actions\StoreQuotationAttachment;
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
        $invitation = $resolve->handle($token, $request);

        $quotation = $storeQuotationAttachment->handle(
            $invitation->tenant,
            null,
            $invitation,
            $request->file('file'),
            QuotationSubmissionSource::VendorPortal,
        );

        return (new QuotationResource($quotation))->response()->setStatusCode(201);
    }

    private function findTenantQuotationByInvitation(Tenant $tenant, int $invitationId): ?Quotation
    {
        return Quotation::query()
            ->with(['attachments' => fn ($query) => $query->with('uploader')->latest('created_at'), 'submittedByUser', 'rfq', 'vendor', 'rfqInvitation'])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_invitation_id', $invitationId)
            ->first();
    }
}
