<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitRequisition
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, Requisition $requisition): Requisition
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            throw new ConflictHttpException('Only draft requisitions can be submitted.');
        }

        $this->validateSubmission($requisition);

        return DB::transaction(function () use ($tenant, $actor, $requisition): Requisition {
            $requisition->forceFill([
                'status' => RequisitionStatus::Submitted,
                'submitted_at' => now(),
            ])->save();

            $this->auditRecorder->record($tenant, $actor, 'requisition.submitted', $requisition, [
                'status' => RequisitionStatus::Submitted->value,
            ]);

            return $requisition->refresh()->load(['requester', 'lineItems']);
        });
    }

    private function validateSubmission(Requisition $requisition): void
    {
        $requisition->loadMissing('lineItems');

        $payload = [
            'title' => $requisition->title,
            'businessJustification' => $requisition->business_justification,
            'neededByDate' => $requisition->needed_by_date?->toDateString(),
            'lineItems' => $requisition->lineItems->map(fn ($lineItem): array => [
                'name' => $lineItem->name,
                'quantity' => $lineItem->quantity,
                'unit' => $lineItem->unit_of_measure,
                'estimatedUnitPrice' => $lineItem->estimated_unit_price,
                'currency' => $lineItem->currency,
            ])->all(),
        ];

        $validator = Validator::make($payload, [
            'title' => ['required', 'string'],
            'businessJustification' => ['required', 'string'],
            'neededByDate' => ['required', 'date'],
            'lineItems' => ['required', 'array', 'min:1'],
            'lineItems.*.name' => ['required', 'string'],
            'lineItems.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lineItems.*.unit' => ['required', 'string'],
            'lineItems.*.estimatedUnitPrice' => ['required', 'numeric', 'gte:0'],
            'lineItems.*.currency' => ['required', 'string', 'size:3'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
