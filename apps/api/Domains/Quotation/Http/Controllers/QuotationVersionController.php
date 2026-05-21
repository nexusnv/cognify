<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Actions\CreateQuotationVersionSnapshot;
use Domains\Quotation\Http\Requests\CreateQuotationRevisionRequest;
use Domains\Quotation\Http\Resources\QuotationVersionResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QuotationVersionController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $quotation): AnonymousResourceCollection
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $this->authorize('view', $model->rfq);

        return QuotationVersionResource::collection(
            QuotationVersion::query()
                ->with(['lineItems', 'submittedByUser', 'quotation.rfq'])
                ->where('tenant_id', $tenant->id)
                ->where('quotation_id', $model->id)
                ->orderByDesc('version_number')
                ->get()
        );
    }

    public function show(CurrentTenant $currentTenant, int $quotation, int $version): QuotationVersionResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $this->authorize('view', $model->rfq);

        return new QuotationVersionResource(
            QuotationVersion::query()
                ->with(['lineItems', 'submittedByUser', 'quotation.rfq'])
                ->where('tenant_id', $tenant->id)
                ->where('quotation_id', $model->id)
                ->findOrFail($version)
        );
    }

    public function store(
        CreateQuotationRevisionRequest $request,
        CurrentTenant $currentTenant,
        int $quotation,
        CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $model->loadMissing(['rfqInvitation', 'rfq', 'vendor']);
        $this->authorize('view', $model->rfq);
        $this->ensureInvitationAcceptsQuotation($model->rfqInvitation);

        $payload = $request->validated();

        $version = DB::transaction(function () use ($tenant, $model, $payload, $request, $createQuotationVersionSnapshot): QuotationVersion {
            $this->ensureAttachmentIdsBelongToQuotation($tenant, $model, $payload['attachmentIds'] ?? []);
            $this->applyRevisionPayload($tenant, $model, $payload, QuotationSubmissionSource::BuyerUpload, $request->user()?->id);

            return $createQuotationVersionSnapshot->handle(
                $tenant,
                $model,
                $request->user(),
                QuotationSubmissionSource::BuyerUpload,
                $payload['attachmentIds'] ?? null,
                ['trigger' => 'buyer_revision'],
            );
        });

        return (new QuotationVersionResource($version))->response()->setStatusCode(201);
    }

    private function findTenantQuotation(Tenant $tenant, int $id): Quotation
    {
        return Quotation::query()
            ->with(['rfq', 'vendor', 'rfqInvitation', 'lineItems', 'currentVersion.lineItems'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function ensureInvitationAcceptsQuotation(?\Domains\Quotation\Models\RfqInvitation $invitation): void
    {
        if ($invitation !== null && in_array($invitation->status, [RfqInvitationStatus::Sent, RfqInvitationStatus::Acknowledged], true)) {
            return;
        }

        throw new ConflictHttpException('This RFQ invitation is not accepting quotation revisions.');
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
    private function applyRevisionPayload(
        Tenant $tenant,
        Quotation $quotation,
        array $payload,
        QuotationSubmissionSource $source,
        ?int $actorId,
    ): void {
        $lineItems = collect($payload['lineItems'] ?? [])->values();
        $missingFields = $this->missingFields($payload, $lineItems->count());

        $quotation->forceFill([
            'quotation_reference' => $payload['quotationReference'] ?? null,
            'status' => QuotationStatus::Received->value,
            'submission_source' => $quotation->submission_source ?? $source->value,
            'submitted_at' => $quotation->submitted_at ?? now(),
            'submitted_by_user_id' => $quotation->submitted_by_user_id ?? $actorId,
            'submitted_by_vendor_contact' => $quotation->submitted_by_vendor_contact,
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
            'buyer_notes' => $payload['buyerNotes'] ?? null,
            'vendor_notes' => $payload['vendorNotes'] ?? null,
            'manual_entry_complete' => $missingFields === [],
            'manual_entry_missing_fields' => $missingFields,
            'manual_entry_saved_at' => now(),
            'manual_entry_saved_source' => $source->value,
            'latest_received_at' => now(),
        ])->save();

        QuotationLineItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('quotation_id', $quotation->id)
            ->delete();

        $lineItems->each(function (array $lineItem, int $index) use ($tenant, $quotation): void {
            QuotationLineItem::query()->create([
                'tenant_id' => $tenant->id,
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

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
