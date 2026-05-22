<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\BuildQuotationComparison;
use Domains\Quotation\Actions\CreateQuotationComparisonNote;
use Domains\Quotation\Actions\DeleteQuotationComparisonNote;
use Domains\Quotation\Actions\UpdateQuotationComparisonNote;
use Domains\Quotation\Http\Requests\SaveQuotationComparisonNoteRequest;
use Domains\Quotation\Http\Resources\QuotationComparisonNoteResource;
use Domains\Quotation\Http\Resources\QuotationComparisonResource;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;
use Illuminate\Http\JsonResponse;

class QuotationComparisonController extends Controller
{
    public function show(CurrentTenant $currentTenant, int $rfq, BuildQuotationComparison $action): QuotationComparisonResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('view', $model);

        return new QuotationComparisonResource($action->handle($tenant, $model));
    }

    public function storeNote(SaveQuotationComparisonNoteRequest $request, CurrentTenant $currentTenant, int $rfq, CreateQuotationComparisonNote $action): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('create', [QuotationComparisonNote::class, $model]);

        return (new QuotationComparisonNoteResource($action->handle($tenant, $request->user(), $model, $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function updateNote(SaveQuotationComparisonNoteRequest $request, CurrentTenant $currentTenant, int $rfq, int $note, UpdateQuotationComparisonNote $action): QuotationComparisonNoteResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $comparisonNote = $this->findTenantNote($tenant, $model, $note);
        $this->authorize('update', $comparisonNote);

        return new QuotationComparisonNoteResource($action->handle($tenant, $request->user(), $model, $comparisonNote, $request->validated()));
    }

    public function deleteNote(CurrentTenant $currentTenant, int $rfq, int $note, DeleteQuotationComparisonNote $action): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $comparisonNote = $this->findTenantNote($tenant, $model, $note);
        $this->authorize('delete', $comparisonNote);

        $action->handle($tenant, request()->user(), $model, $comparisonNote);

        return response()->json(null, 204);
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function findTenantNote(Tenant $tenant, Rfq $rfq, int $id): QuotationComparisonNote
    {
        return QuotationComparisonNote::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
