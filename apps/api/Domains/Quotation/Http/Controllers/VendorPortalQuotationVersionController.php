<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use Domains\Quotation\Actions\ApplyQuotationRevision;
use Domains\Quotation\Actions\CreateQuotationVersionSnapshot;
use Domains\Quotation\Actions\ResolveRfqInvitationPortalAccess;
use Domains\Quotation\Http\Requests\CreateQuotationRevisionRequest;
use Domains\Quotation\Http\Resources\QuotationVersionResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorPortalQuotationVersionController extends Controller
{
    public function index(string $token, ResolveRfqInvitationPortalAccess $resolve, Request $request): AnonymousResourceCollection|JsonResponse
    {
        $request->attributes->set('vendor_portal', true);
        // JsonResource resolves the current request instance when serializing.
        request()->attributes->set('vendor_portal', true);
        $invitation = $resolve->handle($token, $request);
        $request->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());
        request()->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());

        $quotation = $this->findQuotation($invitation);

        if ($quotation === null) {
            return response()->json(['data' => []]);
        }

        return QuotationVersionResource::collection(
            QuotationVersion::query()
                ->with(['lineItems', 'submittedByUser', 'quotation.rfq'])
                ->where('tenant_id', $invitation->tenant_id)
                ->where('quotation_id', $quotation->id)
                ->orderByDesc('version_number')
                ->get()
        );
    }

    public function store(
        string $token,
        CreateQuotationRevisionRequest $request,
        ResolveRfqInvitationPortalAccess $resolve,
        CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
        ApplyQuotationRevision $applyQuotationRevision,
    ): JsonResponse {
        $request->attributes->set('vendor_portal', true);
        // JsonResource resolves the current request instance when serializing.
        request()->attributes->set('vendor_portal', true);
        $invitation = $resolve->handle($token, $request);
        $request->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());
        request()->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());

        $quotation = $this->findQuotation($invitation);

        if ($quotation === null) {
            throw new NotFoundHttpException('A quotation must exist before a revision can be submitted.');
        }

        $payload = $request->validated();

        $version = DB::transaction(function () use ($invitation, $quotation, $payload, $createQuotationVersionSnapshot, $applyQuotationRevision): QuotationVersion {
            $applyQuotationRevision->handle(
                $invitation->tenant,
                $quotation,
                $payload,
                QuotationSubmissionSource::VendorPortal,
                submittedByVendorContact: [
                    'name' => $invitation->contact_name,
                    'email' => $invitation->contact_email,
                ],
                attachmentIds: $payload['attachmentIds'] ?? [],
            );

            return $createQuotationVersionSnapshot->handle(
                $invitation->tenant,
                $quotation,
                null,
                QuotationSubmissionSource::VendorPortal,
                $payload['attachmentIds'] ?? null,
                ['trigger' => 'vendor_portal_revision'],
            );
        });

        return (new QuotationVersionResource($version))->response()->setStatusCode(201);
    }

    private function findQuotation(RfqInvitation $invitation): ?Quotation
    {
        return Quotation::query()
            ->with(['rfq', 'vendor', 'rfqInvitation', 'lineItems', 'currentVersion.lineItems'])
            ->where('tenant_id', $invitation->tenant_id)
            ->where('rfq_invitation_id', $invitation->id)
            ->first();
    }
}
