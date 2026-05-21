<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Actions\CreateQuotationVersionSnapshot;
use Domains\Quotation\Actions\ResolveRfqInvitationPortalAccess;
use Domains\Quotation\Http\Requests\CreateQuotationRevisionRequest;
use Domains\Quotation\Http\Resources\QuotationVersionResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\QuotationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorPortalQuotationVersionController extends Controller
{
    public function index(string $token, ResolveRfqInvitationPortalAccess $resolve, Request $request): AnonymousResourceCollection|JsonResponse
    {
        $request->attributes->set('vendor_portal', true);
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
    ): JsonResponse {
        $request->attributes->set('vendor_portal', true);
        request()->attributes->set('vendor_portal', true);
        $invitation = $resolve->handle($token, $request);
        $request->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());
        request()->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());

        $quotation = $this->findQuotation($invitation);

        if ($quotation === null) {
            throw new NotFoundHttpException('A quotation must exist before a revision can be submitted.');
        }

        $payload = $request->validated();

        $version = DB::transaction(function () use ($invitation, $quotation, $payload, $createQuotationVersionSnapshot): QuotationVersion {
            $this->ensureAttachmentIdsBelongToQuotation($invitation->tenant, $quotation, $payload['attachmentIds'] ?? []);
            $this->applyRevisionPayload($invitation, $quotation, $payload);

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

    /**
     * @param  array<int, int|string>  $attachmentIds
     */
    private function ensureAttachmentIdsBelongToQuotation(Tenant $tenant, Quotation $quotation, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $expectedIds = collect($attachmentIds)->map(fn (int|string $id) => (string) $id)->unique()->values();
        $validCount = Attachment::query()
            ->where('tenant_id', $tenant->id)
            ->where('attachable_type', Quotation::class)
            ->where('attachable_id', $quotation->id)
            ->whereIn('id', $expectedIds->all())
            ->count();

        if ($validCount !== $expectedIds->count()) {
            throw ValidationException::withMessages([
                'attachmentIds' => ['One or more selected attachments do not belong to this quotation.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyRevisionPayload(RfqInvitation $invitation, Quotation $quotation, array $payload): void
    {
        $lineItems = collect($payload['lineItems'] ?? [])->values();
        $missingFields = $this->missingFields($payload, $lineItems->count());

        $quotation->forceFill([
            'quotation_reference' => $payload['quotationReference'] ?? null,
            'status' => QuotationStatus::Received->value,
            'submission_source' => $quotation->submission_source ?? QuotationSubmissionSource::VendorPortal->value,
            'submitted_at' => $quotation->submitted_at ?? now(),
            'submitted_by_user_id' => $quotation->submitted_by_user_id,
            'submitted_by_vendor_contact' => $quotation->submitted_by_vendor_contact ?? [
                'name' => $invitation->contact_name,
                'email' => $invitation->contact_email,
            ],
            'quoted_at' => $payload['quotedAt'] ?? null,
            'valid_until' => $payload['validUntil'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'subtotal_amount' => $payload['subtotalAmount'] ?? null,
            'tax_amount' => $payload['taxAmount'] ?? null,
            'freight_amount' => $payload['freightAmount'] ?? null,
            'discount_amount' => $payload['discountAmount'] ?? null,
            'total_amount' => $payload['totalAmount'] ?? null,
            'payment_terms' => $payload['paymentTerms'] ?? null,
            'delivery_terms' => $payload['deliveryTerms'] ?? null,
            'lead_time_days' => $payload['leadTimeDays'] ?? null,
            'warranty_terms' => $payload['warrantyTerms'] ?? null,
            'exclusions' => $payload['exclusions'] ?? null,
            'compliance_notes' => $payload['complianceNotes'] ?? null,
            'buyer_notes' => $quotation->buyer_notes,
            'vendor_notes' => $payload['vendorNotes'] ?? null,
            'manual_entry_complete' => $missingFields === [],
            'manual_entry_missing_fields' => $missingFields,
            'manual_entry_saved_at' => now(),
            'manual_entry_saved_source' => QuotationSubmissionSource::VendorPortal->value,
            'latest_received_at' => now(),
        ])->save();

        QuotationLineItem::query()
            ->where('tenant_id', $invitation->tenant_id)
            ->where('quotation_id', $quotation->id)
            ->delete();

        $lineItems->each(function (array $lineItem, int $index) use ($invitation, $quotation): void {
            QuotationLineItem::query()->create([
                'tenant_id' => $invitation->tenant_id,
                'quotation_id' => $quotation->id,
                'rfq_line_item_id' => $lineItem['rfqLineItemId'] ?? null,
                'description' => $lineItem['description'],
                'quantity' => $lineItem['quantity'],
                'unit' => $lineItem['unit'] ?? null,
                'unit_price' => $lineItem['unitPrice'] ?? null,
                'subtotal_amount' => $lineItem['subtotalAmount'] ?? null,
                'tax_amount' => $lineItem['taxAmount'] ?? null,
                'total_amount' => $lineItem['totalAmount'] ?? null,
                'lead_time_days' => $lineItem['leadTimeDays'] ?? null,
                'manufacturer' => $lineItem['manufacturer'] ?? null,
                'model_number' => $lineItem['modelNumber'] ?? null,
                'alternate_offered' => $lineItem['alternateOffered'] ?? false,
                'compliance_status' => $lineItem['complianceStatus'] ?? null,
                'notes' => $lineItem['notes'] ?? null,
                'position' => $index + 1,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingFields(array $payload, int $lineItemCount): array
    {
        return collect([
            blank($payload['currency'] ?? null) ? 'currency' : null,
            blank($payload['totalAmount'] ?? null) ? 'totalAmount' : null,
            $lineItemCount === 0 ? 'lineItems' : null,
        ])->filter()->values()->all();
    }
}
