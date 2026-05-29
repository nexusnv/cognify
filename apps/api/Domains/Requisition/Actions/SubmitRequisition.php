<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Auth\TenantRole;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitRequisition
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {
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

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.submitted',
                subject: $requisition,
                metadata: [],
                before: ['status' => RequisitionStatus::Draft->value],
                after: [
                    'status' => RequisitionStatus::Submitted->value,
                    'submittedAt' => $requisition->submitted_at?->toISOString(),
                ],
                subjectDisplay: $requisition->number,
            ));

            $recipients = $tenant->users()
                ->wherePivotIn('role', [TenantRole::Buyer->value, TenantRole::Admin->value])
                ->get()
                ->reject(fn (User $recipient): bool => $recipient->id === $actor->id);

            $this->notificationRecorder->record(
                tenant: $tenant,
                recipients: $recipients,
                data: new NotificationData(
                    type: NotificationPreferenceDefaults::EVENT_REQUISITION_SUBMITTED,
                    title: 'Requisition submitted',
                    body: "{$requisition->number} is ready for procurement review.",
                    href: "/requisitions/{$requisition->id}",
                    subject: $requisition,
                    subjectLabel: $requisition->number,
                    metadata: [
                        'number' => $requisition->number,
                    ],
                    actor: $actor,
                ),
            );

            return $requisition->refresh()->load(['requester', 'lineItems']);
        });
    }

    public function validateSubmission(Requisition $requisition): void
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
