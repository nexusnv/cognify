<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Models\RequisitionLineItem;
use Domains\Requisition\Models\RequisitionTemplate;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApplyRequisitionTemplate
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, Requisition $requisition, RequisitionTemplate $template, string $mode, int $lockVersion): Requisition
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            throw new ConflictHttpException('Only draft requisitions can receive templates.');
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition, $template, $mode, $lockVersion): Requisition {
            $requisition = Requisition::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($requisition->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockVersion !== (int) $requisition->lock_version) {
                throw new ConflictHttpException('The draft has changed since it was loaded.');
            }

            $defaults = $template->defaults ?? [];
            $before = [
                'title' => $requisition->title,
                'businessJustification' => $requisition->business_justification,
                'department' => $requisition->department,
                'costCenter' => $requisition->cost_center,
                'deliveryLocation' => $requisition->delivery_location,
                'currency' => $requisition->currency,
                'lineItemCount' => $requisition->lineItems()->count(),
                'lockVersion' => $requisition->lock_version,
            ];

            $requisition->fill([
                'title' => $this->valueFor($mode, $requisition->title, Arr::get($defaults, 'title')),
                'business_justification' => $this->valueFor($mode, $requisition->business_justification, Arr::get($defaults, 'businessJustification')),
                'department' => $this->valueFor($mode, $requisition->department, Arr::get($defaults, 'department')),
                'cost_center' => $this->valueFor($mode, $requisition->cost_center, Arr::get($defaults, 'costCenter')),
                'delivery_location' => $this->valueFor($mode, $requisition->delivery_location, Arr::get($defaults, 'deliveryLocation')),
                'currency' => strtoupper((string) $this->valueFor($mode, $requisition->currency, Arr::get($defaults, 'currency', $requisition->currency))),
                'lock_version' => $requisition->lock_version + 1,
            ])->save();

            $templateLineItems = Arr::get($defaults, 'lineItems');

            if ($mode === 'replace' && is_array($templateLineItems)) {
                $requisition->lineItems()->delete();
                $this->createTemplateLineItems($requisition, $templateLineItems);
            } elseif (
                $mode === 'fill-empty' &&
                is_array($templateLineItems) &&
                $this->hasBlankPlaceholderLineItems($requisition)
            ) {
                $requisition->lineItems()->delete();
                $this->createTemplateLineItems($requisition, $templateLineItems);
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.template_applied',
                subject: $requisition,
                metadata: [
                    'templateId' => (string) $template->id,
                    'mode' => $mode,
                ],
                before: $before,
                after: [
                    'title' => $requisition->title,
                    'businessJustification' => $requisition->business_justification,
                    'department' => $requisition->department,
                    'costCenter' => $requisition->cost_center,
                    'deliveryLocation' => $requisition->delivery_location,
                    'currency' => $requisition->currency,
                    'lineItemCount' => $requisition->lineItems()->count(),
                    'lockVersion' => $requisition->lock_version,
                ],
                subjectDisplay: $requisition->number,
            ));

            return $requisition->refresh()->load(['requester', 'lineItems']);
        });
    }

    private function valueFor(string $mode, mixed $current, mixed $incoming): mixed
    {
        if ($mode === 'replace') {
            return $incoming ?? $current;
        }

        return blank($current) ? $incoming : $current;
    }

    private function hasBlankPlaceholderLineItems(Requisition $requisition): bool
    {
        $lineItems = $requisition->lineItems()->get();

        if ($lineItems->count() !== 1) {
            return false;
        }

        /** @var RequisitionLineItem $lineItem */
        $lineItem = $lineItems->first();

        return blank($lineItem->name)
            && blank($lineItem->description)
            && (float) $lineItem->quantity === 1.0
            && $lineItem->unit_of_measure === 'each'
            && (float) $lineItem->estimated_unit_price === 0.0
            && strtoupper($lineItem->currency) === 'MYR';
    }

    /**
     * @param array<int, array<string, mixed>> $templateLineItems
     */
    private function createTemplateLineItems(Requisition $requisition, array $templateLineItems): void
    {
        foreach ($templateLineItems as $lineItem) {
            $name = trim((string) Arr::get($lineItem, 'name', ''));

            if ($name === '') {
                continue;
            }

            $requisition->lineItems()->create([
                'name' => $name,
                'description' => Arr::get($lineItem, 'description'),
                'quantity' => Arr::get($lineItem, 'quantity', 1),
                'unit_of_measure' => Arr::get($lineItem, 'unit', 'each'),
                'estimated_unit_price' => Arr::get($lineItem, 'estimatedUnitPrice', 0),
                'currency' => strtoupper((string) Arr::get($lineItem, 'currency', $requisition->currency)),
            ]);
        }
    }
}
