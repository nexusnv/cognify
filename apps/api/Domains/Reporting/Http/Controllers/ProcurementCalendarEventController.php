<?php

namespace Domains\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Domains\Reporting\Http\Requests\ListProcurementCalendarEventsRequest;
use Domains\Reporting\Http\Resources\ProcurementCalendarEventCollectionResource;
use Domains\Reporting\Queries\ListProcurementCalendarEvents;

class ProcurementCalendarEventController extends Controller
{
    public function __invoke(
        ListProcurementCalendarEventsRequest $request,
        CurrentTenant $currentTenant,
        ListProcurementCalendarEvents $query,
    ): ProcurementCalendarEventCollectionResource {
        $payload = $query->handle(
            tenant: $currentTenant->get(),
            actor: $request->user(),
            from: CarbonImmutable::parse((string) $request->input('from'))->startOfDay(),
            to: CarbonImmutable::parse((string) $request->input('to'))->endOfDay(),
            filters: $request->filters(),
        );

        return new ProcurementCalendarEventCollectionResource($payload);
    }
}
