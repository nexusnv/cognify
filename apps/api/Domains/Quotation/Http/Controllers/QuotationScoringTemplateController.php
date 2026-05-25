<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\AmbiguousTenantException;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\CreateQuotationScoringTemplate;
use Domains\Quotation\Actions\DeactivateQuotationScoringTemplate;
use Domains\Quotation\Actions\UpdateQuotationScoringTemplate;
use Domains\Quotation\Http\Requests\SaveQuotationScoringTemplateRequest;
use Domains\Quotation\Http\Resources\QuotationScoringTemplateResource;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuotationScoringTemplateController extends Controller
{
    public function index(CurrentTenant $currentTenant): AnonymousResourceCollection
    {
        $this->authorize('viewAny', QuotationScoringTemplate::class);
        $tenant = $this->tenantOrAbort($currentTenant);

        $templates = QuotationScoringTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->with('criteria')
            ->withCount(['scorecards as usage_count'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return QuotationScoringTemplateResource::collection($templates);
    }

    public function store(
        SaveQuotationScoringTemplateRequest $request,
        CurrentTenant $currentTenant,
        CreateQuotationScoringTemplate $action,
    ): JsonResponse {
        $this->authorize('create', QuotationScoringTemplate::class);
        $tenant = $this->tenantOrAbort($currentTenant);

        return (new QuotationScoringTemplateResource(
            $action->handle($tenant, $request->user(), $request->validated())
        ))->response()->setStatusCode(200);
    }

    public function show(CurrentTenant $currentTenant, QuotationScoringTemplate $quotationScoringTemplate): QuotationScoringTemplateResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $template = $this->findTenantTemplate($tenant, (string) $quotationScoringTemplate->getKey());
        $this->authorize('view', $template);

        return new QuotationScoringTemplateResource($template);
    }

    public function update(
        SaveQuotationScoringTemplateRequest $request,
        CurrentTenant $currentTenant,
        QuotationScoringTemplate $quotationScoringTemplate,
        UpdateQuotationScoringTemplate $action,
    ): QuotationScoringTemplateResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $template = $this->findTenantTemplate($tenant, (string) $quotationScoringTemplate->getKey());
        $this->authorize('update', $template);

        return new QuotationScoringTemplateResource($action->handle($tenant, $request->user(), $template, $request->validated()));
    }

    public function deactivate(
        CurrentTenant $currentTenant,
        QuotationScoringTemplate $quotationScoringTemplate,
        DeactivateQuotationScoringTemplate $action,
    ): QuotationScoringTemplateResource {
        $tenant = $this->tenantOrAbort($currentTenant);
        $template = $this->findTenantTemplate($tenant, (string) $quotationScoringTemplate->getKey());
        $this->authorize('deactivate', $template);

        return new QuotationScoringTemplateResource($action->handle($tenant, request()->user(), $template));
    }

    private function findTenantTemplate(Tenant $tenant, string $id): QuotationScoringTemplate
    {
        return QuotationScoringTemplate::query()
            ->where('tenant_id', $tenant->id)
            ->with('criteria')
            ->withCount(['scorecards as usage_count'])
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $user = request()->user();
        $tenantId = request()->header('X-Tenant-Id');

        if ($user !== null && ($tenantId === null || $tenantId === '')) {
            throw new AmbiguousTenantException('X-Tenant-Id header is required for quotation scoring routes.');
        }

        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
