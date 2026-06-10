<?php

namespace Domains\PurchaseOrder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Actions\CancelPurchaseOrder;
use Domains\PurchaseOrder\Actions\CreatePurchaseOrderFromHandoff;
use Domains\PurchaseOrder\Actions\AcknowledgeIssuedPurchaseOrder;
use Domains\PurchaseOrder\Actions\ExportIssuedPurchaseOrder;
use Domains\PurchaseOrder\Actions\IssuePurchaseOrderToSupplier;
use Domains\PurchaseOrder\Actions\MarkPurchaseOrderReadyForReview;
use Domains\PurchaseOrder\Actions\SubmitPurchaseOrderForApproval;
use Domains\PurchaseOrder\Actions\UpdatePurchaseOrder;
use Domains\PurchaseOrder\Http\Requests\AcknowledgePurchaseOrderRequest;
use Domains\PurchaseOrder\Http\Requests\CancelPurchaseOrderRequest;
use Domains\PurchaseOrder\Http\Requests\IssuePurchaseOrderRequest;
use Domains\PurchaseOrder\Http\Requests\MarkPurchaseOrderReadyForReviewRequest;
use Domains\PurchaseOrder\Http\Requests\SubmitPurchaseOrderApprovalRequest;
use Domains\PurchaseOrder\Http\Requests\UpdatePurchaseOrderRequest;
use Domains\PurchaseOrder\Http\Resources\PurchaseOrderResource;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorize('viewAny', PurchaseOrder::class);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::enum(PurchaseOrderStatus::class)],
            'vendorId' => ['sometimes', 'string'],
            'requisitionId' => ['sometimes', 'string'],
            'projectId' => ['sometimes', 'string'],
            'requesterId' => ['sometimes', 'string'],
            'requestedByUserId' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string'],
            'updatedFrom' => ['sometimes', 'date'],
            'updatedTo' => ['sometimes', 'date'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->with('lines');

        $query
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['vendorId'] ?? null, fn ($query, string $vendorId) => $query->where('vendor_id', $vendorId))
            ->when($validated['requisitionId'] ?? null, fn ($query, string $requisitionId) => $query->where('requisition_id', $requisitionId))
            ->when($validated['projectId'] ?? null, fn ($query, string $projectId) => $query->where('project_id', $projectId))
            ->when(
                $validated['requestedByUserId'] ?? $validated['requesterId'] ?? null,
                fn ($query, string $requesterId) => $query->whereHas(
                    'handoff',
                    fn ($handoffQuery) => $handoffQuery->where('requested_by_user_id', $requesterId),
                )
            )
            ->when($validated['updatedFrom'] ?? null, fn ($query, string $date) => $query->whereDate('updated_at', '>=', $date))
            ->when($validated['updatedTo'] ?? null, fn ($query, string $date) => $query->whereDate('updated_at', '<=', $date))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhereRaw($this->sourceSnapshotTextSearchSql(), ["%{$search}%"]);
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $paginator = $query->paginate((int) ($validated['perPage'] ?? 15));

        return response()->json([
            'data' => PurchaseOrderResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(CurrentTenant $currentTenant, Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('view', $purchaseOrder);

        return $this->resourceResponse($request, $purchaseOrder);
    }

    public function createFromHandoff(
        CurrentTenant $currentTenant,
        Request $request,
        PurchaseOrderRequestHandoff $handoff,
        CreatePurchaseOrderFromHandoff $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $handoff = $this->findTenantHandoff($tenant, $handoff);
        $this->authorize('createFromHandoff', [PurchaseOrder::class, $handoff]);

        $result = $action->handle($handoff, $request->user());

        return $this->resourceResponse($request, $result['purchaseOrder'], $result['created'] ? 201 : 200);
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        UpdatePurchaseOrder $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('update', $purchaseOrder);

        return $this->resourceResponse($request, $action->handle($purchaseOrder, $request->user(), $request->validated()));
    }

    public function readyForReview(
        MarkPurchaseOrderReadyForReviewRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        MarkPurchaseOrderReadyForReview $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('markReadyForReview', $purchaseOrder);

        return $this->resourceResponse(
            $request,
            $action->handle($purchaseOrder, $request->user(), (int) $request->validated('lockVersion')),
        );
    }

    public function submitApproval(
        SubmitPurchaseOrderApprovalRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        SubmitPurchaseOrderForApproval $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('submitApproval', $purchaseOrder);

        return $this->resourceResponse(
            $request,
            $action->handle($purchaseOrder, $request->user(), (int) $request->validated('lockVersion')),
        );
    }

    public function cancel(
        CancelPurchaseOrderRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        CancelPurchaseOrder $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('cancel', $purchaseOrder);

        return $this->resourceResponse(
            $request,
            $action->handle(
                $purchaseOrder,
                $request->user(),
                (int) $request->validated('lockVersion'),
                (string) $request->validated('reason'),
            ),
        );
    }

    public function issue(
        IssuePurchaseOrderRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        IssuePurchaseOrderToSupplier $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('issueToSupplier', $purchaseOrder);

        return $this->resourceResponse($request, $action->handle($purchaseOrder, $request->user(), $request->validated()));
    }

    public function exportSupplierJson(
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        ExportIssuedPurchaseOrder $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('exportSupplierVersion', $purchaseOrder);

        return response()->json($action->handle($purchaseOrder, request()->user(), 'json', recordExport: false));
    }

    public function recordSupplierExportJson(
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        ExportIssuedPurchaseOrder $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('exportSupplierVersion', $purchaseOrder);

        return response()->json($action->handle($purchaseOrder, request()->user(), 'json'));
    }

    public function exportSupplierCsv(
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        ExportIssuedPurchaseOrder $action,
    ): Response {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('exportSupplierVersion', $purchaseOrder);

        $csv = $action->handle($purchaseOrder, request()->user(), 'csv', recordExport: false);

        return $this->csvResponse($purchaseOrder, $csv);
    }

    public function recordSupplierExportCsv(
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        ExportIssuedPurchaseOrder $action,
    ): Response {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('exportSupplierVersion', $purchaseOrder);

        $csv = $action->handle($purchaseOrder, request()->user(), 'csv');

        return $this->csvResponse($purchaseOrder, $csv);
    }

    public function acknowledge(
        AcknowledgePurchaseOrderRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
        AcknowledgeIssuedPurchaseOrder $action,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('acknowledgeSupplier', $purchaseOrder);

        return $this->resourceResponse($request, $action->handle($purchaseOrder, $request->user(), $request->validated()));
    }

    private function csvResponse(PurchaseOrder $purchaseOrder, string $csv): Response
    {
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$purchaseOrder->number.'-supplier.csv"',
        ]);
    }

    private function findTenantPurchaseOrder(Tenant $tenant, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $tenantPurchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($purchaseOrder->id)
            ->with('lines')
            ->first();

        if ($tenantPurchaseOrder === null) {
            abort(403, 'You are not allowed to access this purchase order.');
        }

        return $tenantPurchaseOrder;
    }

    private function findTenantHandoff(Tenant $tenant, PurchaseOrderRequestHandoff $handoff): PurchaseOrderRequestHandoff
    {
        $tenantHandoff = PurchaseOrderRequestHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($handoff->id)
            ->first();

        if ($tenantHandoff === null) {
            abort(403, 'You are not allowed to access this PO handoff.');
        }

        return $tenantHandoff;
    }

    private function resourceResponse(Request $request, PurchaseOrder $purchaseOrder, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => (new PurchaseOrderResource($purchaseOrder))->resolve($request),
        ], $status);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }

    private function sourceSnapshotTextSearchSql(): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'source_snapshot::text LIKE ?'
            : 'CAST(source_snapshot AS TEXT) LIKE ?';
    }
}
