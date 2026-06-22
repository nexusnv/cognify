<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\CreateSupplierCreditMemo;
use Domains\CreditMemo\Actions\PostSupplierCreditMemo;
use Domains\CreditMemo\Actions\SubmitSupplierCreditMemoForApproval;
use Domains\CreditMemo\Actions\UpdateSupplierCreditMemo;
use Domains\CreditMemo\Actions\VoidSupplierCreditMemo;
use Domains\CreditMemo\Http\Requests\CreateSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Requests\PostSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Requests\SubmitSupplierCreditMemoForApprovalRequest;
use Domains\CreditMemo\Http\Requests\UpdateSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Requests\VoidSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoResource;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierCreditMemoController
{
    use AuthorizesRequests;

    public function index(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);

        $query = SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->with(['vendor']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($vendorId = $request->query('vendorId')) {
            $query->where('vendor_id', $vendorId);
        }

        $perPage = min(max((int) $request->integer('perPage', 25), 1), 100);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => SupplierCreditMemoResource::collection($paginator),
            'meta' => [
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
                'currentPage' => $paginator->currentPage(),
            ],
        ]);
    }

    public function store(
        CreateSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        CreateSupplierCreditMemo $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);

        $prototype = new SupplierCreditMemo(['tenant_id' => $tenant->id]);
        $this->authorize('create', $prototype);

        $validated = $request->validated();

        $lines = [];
        foreach ($validated['lines'] as $line) {
            $lines[] = [
                'line_number' => $line['lineNumber'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unitPrice'],
                'tax_code' => $line['taxCode'] ?? null,
                'tax_amount' => $line['taxAmount'] ?? null,
                'purchase_order_line_id' => $line['purchaseOrderLineId'] ?? null,
                'original_invoice_line_id' => $line['originalInvoiceLineId'] ?? null,
                'notes' => $line['notes'] ?? null,
            ];
        }

        $creditMemo = $action->handle(
            $tenant,
            $request->user(),
            (int) $validated['vendorId'],
            $validated['originalInvoiceId'] ?? null,
            $validated['vendorCreditMemoNumber'],
            $validated['creditDate'],
            $validated['currency'] ?? 'USD',
            $validated['subtotalAmount'],
            $validated['taxAmount'],
            $validated['freightAmount'],
            $validated['totalAmount'],
            $lines,
            $validated['notes'] ?? null,
        );

        return (new SupplierCreditMemoResource($creditMemo->fresh(['lines', 'exceptions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CurrentTenant $currentTenant, SupplierCreditMemo $creditMemo): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('view', $creditMemo);

        $creditMemo->loadMissing(['lines', 'applications', 'exceptions', 'vendor', 'originalInvoice']);

        return response()->json([
            'data' => (new SupplierCreditMemoResource($creditMemo))->resolve(request()),
        ]);
    }

    public function update(
        UpdateSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        UpdateSupplierCreditMemo $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('update', $creditMemo);

        $validated = $request->validated();

        $creditMemo = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $validated['lockVersion'],
            $validated['notes'] ?? null,
            $validated['creditDate'] ?? null,
            $validated['vendorCreditMemoNumber'] ?? null,
        );

        return response()->json([
            'data' => (new SupplierCreditMemoResource($creditMemo))->resolve($request),
        ]);
    }

    public function submit(
        SubmitSupplierCreditMemoForApprovalRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SubmitSupplierCreditMemoForApproval $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('submit', $creditMemo);

        $validated = $request->validated();

        $creditMemo = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $validated['lockVersion'],
        );

        return response()->json([
            'data' => (new SupplierCreditMemoResource($creditMemo))->resolve($request),
        ]);
    }

    public function post(
        PostSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        PostSupplierCreditMemo $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('post', $creditMemo);

        $validated = $request->validated();

        $creditMemo = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $validated['lockVersion'],
        );

        return response()->json([
            'data' => (new SupplierCreditMemoResource($creditMemo))->resolve($request),
        ]);
    }

    public function void(
        VoidSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        VoidSupplierCreditMemo $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantCreditMemo($tenant, $creditMemo);
        $this->authorize('void', $creditMemo);

        $validated = $request->validated();

        $creditMemo = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $validated['lockVersion'],
            $validated['voidReason'],
        );

        return response()->json([
            'data' => (new SupplierCreditMemoResource($creditMemo))->resolve($request),
        ]);
    }

    private function findTenantCreditMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        return SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($creditMemo->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
