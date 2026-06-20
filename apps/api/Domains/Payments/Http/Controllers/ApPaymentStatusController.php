<?php

namespace Domains\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Http\Resources\ApPaymentHandoffResource;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Payments\Actions\CloseApPaymentHandoffWithVariance;
use Domains\Payments\Actions\MarkApPaymentHandoffFailed;
use Domains\Payments\Actions\MarkApPaymentHandoffPaid;
use Domains\Payments\Actions\RescheduleFailedApPaymentHandoff;
use Domains\Payments\Actions\ScheduleApPaymentHandoff;
use Domains\Payments\Actions\VoidApPaymentHandoff;
use Domains\Payments\Http\Requests\CloseApPaymentHandoffWithVarianceRequest;
use Domains\Payments\Http\Requests\MarkApPaymentHandoffFailedRequest;
use Domains\Payments\Http\Requests\MarkApPaymentHandoffPaidRequest;
use Domains\Payments\Http\Requests\RescheduleApPaymentHandoffRequest;
use Domains\Payments\Http\Requests\ScheduleApPaymentHandoffRequest;
use Domains\Payments\Http\Requests\VoidApPaymentHandoffRequest;
use Domains\Payments\States\ApPaymentFailureCode;
use Illuminate\Http\JsonResponse;

class ApPaymentStatusController extends Controller
{
    public function schedule(
        ScheduleApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        ScheduleApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('schedule', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            scheduledForDate: $request->validated('scheduledForDate'),
            paymentReference: $request->validated('paymentReference'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function markPaid(
        MarkApPaymentHandoffPaidRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        MarkApPaymentHandoffPaid $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('markPaid', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            remittanceReference: $request->validated('remittanceReference'),
            remittanceAdviceSentAt: $request->validated('remittanceAdviceSentAt'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function closeWithVariance(
        CloseApPaymentHandoffWithVarianceRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        CloseApPaymentHandoffWithVariance $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('closeWithVariance', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            varianceReason: (string) $request->validated('varianceReason'),
            remittanceReference: $request->validated('remittanceReference'),
            remittanceAdviceSentAt: $request->validated('remittanceAdviceSentAt'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function markFailed(
        MarkApPaymentHandoffFailedRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        MarkApPaymentHandoffFailed $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('markFailed', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            failureCode: ApPaymentFailureCode::from((string) $request->validated('failureCode')),
            failureReason: (string) $request->validated('failureReason'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function void(
        VoidApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        VoidApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('void', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            voidReason: (string) $request->validated('voidReason'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function reschedule(
        RescheduleApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        RescheduleFailedApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('reschedule', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (int) $request->validated('lockVersion'),
            scheduledForDate: $request->validated('scheduledForDate'),
            paymentReference: $request->validated('paymentReference'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
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
