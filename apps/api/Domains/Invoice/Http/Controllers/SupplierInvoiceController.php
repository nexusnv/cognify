<?php

namespace Domains\Invoice\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Invoice\Actions\CaptureSupplierInvoice;
use Domains\Invoice\Http\Requests\CaptureSupplierInvoiceRequest;
use Domains\Invoice\Http\Resources\SupplierInvoiceQueueResource;
use Domains\Invoice\Http\Resources\SupplierInvoiceResource;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Support\SupplierInvoiceNumber;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SupplierInvoiceController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CaptureSupplierInvoice $captureSupplierInvoice,
    ) {}

    public function queue(CurrentTenant $currentTenant, Request $request): ResourceCollection
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $prototype = new SupplierInvoice([
            'tenant_id' => $tenant->id,
        ]);
        $this->authorize('review', $prototype);

        $invoiceClass = SupplierInvoice::class;
        $query = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->with(['purchaseOrder', 'vendor', 'lines'])
            ->selectRaw(
                'supplier_invoices.*, (SELECT COUNT(*) FROM attachments WHERE CAST(supplier_invoices.id AS TEXT) = attachments.attachable_id AND attachments.attachable_type = ? AND attachments.tenant_id = ? AND attachments.deleted_at IS NULL) as attachments_count',
                [$invoiceClass, $tenant->id]
            );

        $this->applyQueueFilters($query, $request);

        $invoices = $query->paginate(min(max((int) $request->integer('perPage', 25), 1), 100));

        return SupplierInvoiceQueueResource::collection($invoices);
    }

    public function index(CurrentTenant $currentTenant, PurchaseOrder $purchaseOrder): ResourceCollection
    {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('view', $purchaseOrder);

        $invoices = SupplierInvoice::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->with('lines')
            ->orderByDesc('invoice_date')
            ->orderByDesc('created_at')
            ->get();

        return SupplierInvoiceResource::collection($invoices);
    }

    public function store(
        CaptureSupplierInvoiceRequest $request,
        CurrentTenant $currentTenant,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse {
        $purchaseOrder = $this->findTenantPurchaseOrder($this->tenantOrAbort($currentTenant), $purchaseOrder);
        $this->authorize('captureInvoice', $purchaseOrder);

        try {
            $invoice = $this->captureSupplierInvoice->handle(
                purchaseOrder: $purchaseOrder,
                actor: $request->user(),
                payload: $request->validated(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'lines' => [$e->getMessage()],
            ]);
        }

        return (new SupplierInvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function show(CurrentTenant $currentTenant, SupplierInvoice $supplierInvoice, Request $request): JsonResponse
    {
        $supplierInvoice = $this->findTenantSupplierInvoice($this->tenantOrAbort($currentTenant), $supplierInvoice);
        $this->authorize('view', $supplierInvoice);

        $supplierInvoice->loadMissing(['lines', 'purchaseOrder', 'vendor']);
        $supplierInvoice->loadCount('attachments');

        return response()->json([
            'data' => (new SupplierInvoiceResource($supplierInvoice))->resolve($request),
        ]);
    }

    private function applyQueueFilters(Builder $query, Request $request): void
    {
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($vendorId = $request->query('vendorId')) {
            $query->where('vendor_id', $vendorId);
        }

        if ($invoiceNumber = $request->query('invoiceNumber')) {
            $query->where('invoice_number_normalized', 'like', '%'.SupplierInvoiceNumber::normalize((string) $invoiceNumber).'%');
        }

        if ($purchaseOrderNumber = $request->query('purchaseOrderNumber')) {
            $query->whereHas('purchaseOrder', fn (Builder $poQuery) => $poQuery->where('number', 'like', '%'.$purchaseOrderNumber.'%'));
        }

        if ($dueBefore = $request->query('dueBefore')) {
            if (! is_string($dueBefore) || ! $this->isValidDate($dueBefore, 'Y-m-d')) {
                throw ValidationException::withMessages(['dueBefore' => ['The due before date must be a valid date.']]);
            }

            $query->whereDate('due_date', '<=', $dueBefore);
        }

        if ($request->boolean('requiresAttachment')) {
            $query->doesntHave('attachments');
        }

        if ($matchingStatus = $request->query('matchingStatus')) {
            $query->where('matching_status', $matchingStatus);
        }

        if ($request->boolean('hasMismatch')) {
            $query->where('matching_status', 'mismatch');
        }

        if ($paymentStatus = $request->query('paymentStatus')) {
            $validPaymentStatuses = ['none', 'any', 'payment_eligible', 'on_hold', 'payment_ready', 'handoff_exported', 'payment_scheduled', 'partially_paid', 'paid', 'reversed'];

            if (! in_array($paymentStatus, $validPaymentStatuses, true)) {
                throw ValidationException::withMessages([
                    'paymentStatus' => ['The payment status filter is invalid.'],
                ]);
            }

            if ($paymentStatus === 'none') {
                $query->whereNull('payment_status');
            } elseif ($paymentStatus === 'any') {
                $query->whereNotNull('payment_status');
            } else {
                $query->where('payment_status', $paymentStatus);
            }
        }

        if ($reviewBlocker = $request->query('reviewBlocker')) {
            $query->whereJsonContains('review_blockers', [['key' => $reviewBlocker]]);
        }

        $sort = $request->query('sort', 'due_date_asc');

        if (! is_string($sort) || ! in_array($sort, ['due_date_asc', 'due_date_desc', 'created_at_asc', 'created_at_desc'], true)) {
            $sort = 'due_date_asc';
        }

        match ($sort) {
            'due_date_desc' => $query->orderByDesc('due_date')->orderByDesc('created_at'),
            'created_at_asc' => $query->orderBy('created_at'),
            'created_at_desc' => $query->orderByDesc('created_at'),
            default => $query->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('created_at'),
        };
    }

    private function isValidDate(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private function findTenantPurchaseOrder(Tenant $tenant, PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $tenantPurchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($purchaseOrder->id)
            ->first();

        if ($tenantPurchaseOrder === null) {
            abort(403, 'You are not allowed to access this purchase order.');
        }

        return $tenantPurchaseOrder;
    }

    private function findTenantSupplierInvoice(Tenant $tenant, SupplierInvoice $supplierInvoice): SupplierInvoice
    {
        return SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->with(['lines', 'purchaseOrder', 'vendor'])
            ->findOrFail($supplierInvoice->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
