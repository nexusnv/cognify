<?php

namespace Domains\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Payments\Actions\DiscardPaymentImportRow;
use Domains\Payments\Actions\MatchPaymentImportRow;
use Domains\Payments\Actions\ParsePaymentImportFile;
use Domains\Payments\Actions\ReconcilePaymentImportBatch;
use Domains\Payments\Http\Requests\ReconcilePaymentImportBatchRequest;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\Http\Requests\UpdatePaymentImportRowRequest;
use Domains\Payments\Http\Requests\UploadPaymentImportRequest;
use Domains\Payments\Http\Resources\ApPaymentImportResource;
use Domains\Payments\Models\ApPaymentImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApPaymentImportController extends Controller
{
    public function upload(
        UploadPaymentImportRequest $request,
        CurrentTenant $currentTenant,
        ParsePaymentImportFile $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorize('upload', ApPaymentImport::class);

        $preview = $action->handle($request->file('file'), $request->user());

        $rows = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $preview->batchId)
            ->orderBy('row_index')
            ->get();

        return response()->json([
            'data' => [
                'batchId' => $preview->batchId,
                'rows' => ApPaymentImportResource::collection($rows)->resolve(),
                'summary' => [
                    'total' => $rows->count(),
                    'pending' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Pending)->count(),
                    'reconciled' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Reconciled)->count(),
                    'failed' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Failed)->count(),
                    'discarded' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Discarded)->count(),
                ],
            ],
        ], 201);
    }

    public function show(CurrentTenant $currentTenant, string $batchId): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $rows = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->orderBy('row_index')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Import batch not found.');
        }

        $this->authorize('view', $rows->first());

        return response()->json([
            'data' => [
                'batchId' => $batchId,
                'rows' => ApPaymentImportResource::collection($rows)->resolve(),
                'summary' => [
                    'total' => $rows->count(),
                    'pending' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Pending)->count(),
                    'reconciled' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Reconciled)->count(),
                    'failed' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Failed)->count(),
                    'discarded' => $rows->filter(fn ($row) => $row->status === ApPaymentImportStatus::Discarded)->count(),
                ],
            ],
        ]);
    }

    public function update(
        UpdatePaymentImportRowRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentImport $import,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantImport = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($import->id)
            ->first();
        if ($tenantImport === null) {
            abort(403, 'You are not allowed to access this import row.');
        }
        $this->authorize('update', $tenantImport);

        $tenantImport->assertLockVersion((int) $request->validated('lockVersion'));

        $tenantImport->forceFill(array_filter([
            'handoff_number' => $request->validated('handoffNumber'),
            'invoice_number' => $request->validated('invoiceNumber'),
            'allocated_amount' => $request->validated('allocatedAmount'),
            'mark_full' => $request->validated('markFull'),
            'settlement_amount' => $request->validated('settlementAmount'),
            'settlement_currency' => $request->validated('settlementCurrency') !== null
                ? strtoupper((string) $request->validated('settlementCurrency'))
                : null,
            'paid_at' => $request->validated('paidAt'),
            'settlement_method' => $request->validated('settlementMethod'),
            'failure_code' => $request->validated('failureCode'),
            'failure_reason' => $request->validated('failureReason'),
            'void_reason' => $request->validated('voidReason'),
        ], fn ($v) => $v !== null))->save();

        $matched = app(MatchPaymentImportRow::class)->handle($tenantImport->fresh());

        return response()->json([
            'data' => (new ApPaymentImportResource($matched))->resolve(),
        ]);
    }

    public function reconcile(
        ReconcilePaymentImportBatchRequest $request,
        CurrentTenant $currentTenant,
        string $batchId,
        ReconcilePaymentImportBatch $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorize('reconcile', ApPaymentImport::class);

        $result = $action->handle(
            $batchId,
            $request->user(),
            $request->validated('lockVersions'),
        );

        return response()->json([
            'data' => [
                'reconciled' => $result->reconciled,
                'failed' => $result->failed,
                'skipped' => $result->skipped,
            ],
        ]);
    }

    public function discard(
        Request $request,
        CurrentTenant $currentTenant,
        ApPaymentImport $import,
        DiscardPaymentImportRow $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantImport = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($import->id)
            ->first();
        if ($tenantImport === null) {
            abort(403, 'You are not allowed to access this import row.');
        }
        $this->authorize('discard', $tenantImport);

        $result = $action->handle($tenantImport, $request->user());

        return response()->json([
            'data' => (new ApPaymentImportResource($result))->resolve(),
        ]);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
