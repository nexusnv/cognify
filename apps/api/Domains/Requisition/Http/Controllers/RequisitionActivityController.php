<?php

namespace Domains\Requisition\Http\Controllers;

use App\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Http\Resources\RequisitionActivityResource;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Request;

class RequisitionActivityController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant, int $requisition)
    {
        $requisition = Requisition::query()
            ->where('tenant_id', $currentTenant->get()->id)
            ->findOrFail($requisition);

        $this->authorize('view', $requisition);

        $events = AuditEvent::query()
            ->with('actor')
            ->where('tenant_id', $currentTenant->get()->id)
            ->where('subject_type', Requisition::class)
            ->where('subject_id', $requisition->id)
            ->oldest('occurred_at')
            ->get();

        return RequisitionActivityResource::collection($events);
    }
}
