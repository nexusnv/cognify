<?php

namespace Domains\AccountsPayable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Actions\CancelApPaymentHandoff;
use Domains\AccountsPayable\Actions\CreateApPaymentHandoff;
use Domains\AccountsPayable\Actions\ExportApPaymentHandoff;
use Domains\AccountsPayable\Actions\MarkApPaymentHandoffReady;
use Domains\AccountsPayable\Actions\RefreshApPaymentHandoffSnapshot;
use Domains\AccountsPayable\Actions\UpdateApPaymentHandoff;
use Domains\AccountsPayable\Http\Requests\CancelApPaymentHandoffRequest;
use Domains\AccountsPayable\Http\Requests\CreateApPaymentHandoffRequest;
use Domains\AccountsPayable\Http\Requests\MarkApPaymentHandoffReadyRequest;
use Domains\AccountsPayable\Http\Requests\RefreshApPaymentHandoffSnapshotRequest;
use Domains\AccountsPayable\Http\Requests\UpdateApPaymentHandoffRequest;
use Domains\AccountsPayable\Http\Resources\ApPaymentHandoffResource;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ApPaymentHandoffController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorize('create', ApPaymentHandoff::class);

        $handoffs = ApPaymentHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->with('invoices')
            ->paginate(min(max((int) $request->input('perPage', 25), 1), 100));

        return response()->json([
            'data' => ApPaymentHandoffResource::collection($handoffs),
            'meta' => [
                'currentPage' => $handoffs->currentPage(),
                'lastPage' => $handoffs->lastPage(),
                'perPage' => $handoffs->perPage(),
                'total' => $handoffs->total(),
            ],
        ]);
    }

    public function show(CurrentTenant $currentTenant, ApPaymentHandoff $handoff): JsonResponse
    {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('view', $handoff);

        return $this->resourceResponse($handoff);
    }

    public function store(
        CreateApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        CreateApPaymentHandoff $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorize('create', ApPaymentHandoff::class);

        $invoiceIds = array_values(array_unique((array) $request->validated('invoiceIds')));

        $invoices = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $invoiceIds)
            ->get();

        if ($invoices->count() !== count($invoiceIds)) {
            throw ValidationException::withMessages([
                'invoiceIds' => 'One or more invoices were not found or do not belong to the current tenant.',
            ]);
        }

        $handoff = $action->handle(
            $invoices->all(),
            $request->user(),
            $request->validated('notes'),
            $request->validated('effectivePaymentDate'),
        );

        return response()->json([
            'data' => (new ApPaymentHandoffResource($handoff))->resolve(),
        ], 201);
    }

    public function update(
        UpdateApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        UpdateApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('update', $handoff);

        return $this->resourceResponse($action->handle($handoff, $request->user(), $request->validated()));
    }

    public function refresh(
        RefreshApPaymentHandoffSnapshotRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        RefreshApPaymentHandoffSnapshot $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('refresh', $handoff);

        $lockVersion = $request->validated('lockVersion');

        return $this->resourceResponse($action->handle($handoff, $request->user(), $lockVersion !== null ? (int) $lockVersion : null));
    }

    public function markReady(
        MarkApPaymentHandoffReadyRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        MarkApPaymentHandoffReady $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('markReady', $handoff);

        return $this->resourceResponse($action->handle($handoff, $request->user(), (int) $request->validated('lockVersion')));
    }

    public function destroy(
        CancelApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        CancelApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('cancel', $handoff);

        return $this->resourceResponse($action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            (string) $request->validated('reason'),
        ));
    }

    public function exportJson(
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        ExportApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('export', $handoff);

        return response()->json($action->handle($handoff, request()->user(), 'json', recordExport: false));
    }

    public function recordExportJson(
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        ExportApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('export', $handoff);

        return response()->json($action->handle($handoff, request()->user(), 'json'));
    }

    public function exportCsv(
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        ExportApPaymentHandoff $action,
    ): Response {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('export', $handoff);

        $csv = $action->handle($handoff, request()->user(), 'csv', recordExport: false);

        return $this->csvResponse($handoff, $csv);
    }

    public function recordExportCsv(
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        ExportApPaymentHandoff $action,
    ): Response {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('export', $handoff);

        $csv = $action->handle($handoff, request()->user(), 'csv');

        return $this->csvResponse($handoff, $csv);
    }

    private function csvResponse(ApPaymentHandoff $handoff, string $csv): Response
    {
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$handoff->number.'.csv"',
        ]);
    }

    private function findTenantHandoff(Tenant $tenant, ApPaymentHandoff $handoff): ApPaymentHandoff
    {
        $tenantHandoff = ApPaymentHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($handoff->id)
            ->first();

        if ($tenantHandoff === null) {
            abort(403, 'You are not allowed to access this AP payment handoff.');
        }

        return $tenantHandoff;
    }

    private function resourceResponse(?ApPaymentHandoff $handoff): JsonResponse
    {
        return response()->json([
            'data' => $handoff === null ? null : (new ApPaymentHandoffResource($handoff))->resolve(),
        ]);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
