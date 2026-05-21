<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\ApplyQuotationRevision;
use Domains\Quotation\Actions\CreateQuotationVersionSnapshot;
use Domains\Quotation\Http\Requests\CreateQuotationRevisionRequest;
use Domains\Quotation\Http\Resources\QuotationVersionResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\RfqInvitationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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
        ApplyQuotationRevision $applyQuotationRevision,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $model->loadMissing(['rfqInvitation', 'rfq', 'vendor']);
        $this->authorize('update', $model->rfq);
        $this->ensureInvitationAcceptsQuotation($model->rfqInvitation);

        $payload = $request->validated();

        $version = DB::transaction(function () use ($tenant, $model, $payload, $request, $createQuotationVersionSnapshot, $applyQuotationRevision): QuotationVersion {
            $applyQuotationRevision->handle(
                $tenant,
                $model,
                $payload,
                QuotationSubmissionSource::BuyerUpload,
                $request->user()?->id,
                attachmentIds: $payload['attachmentIds'] ?? [],
            );

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

    private function ensureInvitationAcceptsQuotation(?RfqInvitation $invitation): void
    {
        if ($invitation !== null && in_array($invitation->status, [RfqInvitationStatus::Sent, RfqInvitationStatus::Acknowledged], true)) {
            return;
        }

        throw new ConflictHttpException('This RFQ invitation is not accepting quotation revisions.');
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
