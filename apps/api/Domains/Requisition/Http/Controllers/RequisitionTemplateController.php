<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Http\Resources\RequisitionTemplateResource;
use Domains\Requisition\Models\RequisitionTemplate;

class RequisitionTemplateController extends Controller
{
    public function index(CurrentTenant $currentTenant)
    {
        $tenant = $currentTenant->get();

        $templates = RequisitionTemplate::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return RequisitionTemplateResource::collection($templates);
    }
}
