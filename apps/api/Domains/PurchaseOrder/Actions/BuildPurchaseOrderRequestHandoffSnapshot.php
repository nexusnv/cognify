<?php

namespace Domains\PurchaseOrder\Actions;

use Domains\Approval\Models\ApprovalStage;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\Models\RfqAwardRecommendation;

class BuildPurchaseOrderRequestHandoffSnapshot
{
    /**
     * @return array{source: array<string, mixed>, lines: array<int, array<string, mixed>>, approval: array<string, mixed>, evidence: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function handle(RfqAwardRecommendation $recommendation): array
    {
        $recommendation->loadMissing([
            'tenant',
            'rfq.requisition.requester',
            'rfq.project',
            'recommendedVendor',
            'recommendedQuotation',
            'recommendedQuotationVersion.lineItems',
            'scorecard.criteria',
            'scorecard.entries',
            'approvalInstance.stages.tasks.assignee',
            'approvalInstance.stages.tasks.decidedBy',
            'evidenceReferences',
            'approvedByUser',
        ]);

        $rfq = $recommendation->rfq;
        $vendor = $recommendation->recommendedVendor;
        $quotation = $recommendation->recommendedQuotation;
        $version = $recommendation->recommendedQuotationVersion;
        $approvalInstance = $recommendation->approvalInstance;

        $warnings = [];

        if ($version === null || $version->lineItems->isEmpty()) {
            $warnings[] = 'No quotation line items are available for the selected award.';
        }

        if ($approvalInstance === null || $approvalInstance->status !== ApprovalInstanceStatus::Approved) {
            $warnings[] = 'Award approval has not completed.';
        }

        return [
            'source' => [
                'rfq' => $rfq ? [
                    'id' => (string) $rfq->id,
                    'number' => $rfq->number,
                    'title' => $rfq->title,
                    'scopeSummary' => $rfq->scope_summary,
                    'requisition' => $rfq->requisition ? [
                        'id' => (string) $rfq->requisition->id,
                        'number' => $rfq->requisition->number,
                        'title' => $rfq->requisition->title,
                        'requesterName' => $rfq->requisition->requester?->name,
                    ] : null,
                    'project' => $rfq->project ? [
                        'id' => (string) $rfq->project->id,
                        'number' => $rfq->project->number,
                        'name' => $rfq->project->name,
                    ] : null,
                ] : null,
                'vendor' => $vendor ? [
                    'id' => (string) $vendor->id,
                    'name' => $vendor->name,
                    'status' => $vendor->status,
                ] : null,
                'quotation' => $quotation ? [
                    'id' => (string) $quotation->id,
                    'number' => $quotation->number,
                    'currency' => $quotation->currency,
                    'totalAmount' => $this->decimal($quotation->total_amount, 2),
                    'paymentTerms' => $quotation->payment_terms,
                    'deliveryTerms' => $quotation->delivery_terms,
                    'warrantyTerms' => $quotation->warranty_terms,
                    'leadTimeDays' => $quotation->lead_time_days,
                ] : null,
                'quotationVersion' => $version ? [
                    'id' => (string) $version->id,
                    'versionNumber' => $version->version_number,
                    'currency' => $version->currency,
                    'subtotalAmount' => $this->decimal($version->subtotal_amount, 2),
                    'taxAmount' => $this->decimal($version->tax_amount, 2),
                    'freightAmount' => $this->decimal($version->freight_amount, 2),
                    'discountAmount' => $this->decimal($version->discount_amount, 2),
                    'totalAmount' => $this->decimal($version->total_amount, 2),
                    'paymentTerms' => $version->payment_terms,
                    'deliveryTerms' => $version->delivery_terms,
                    'warrantyTerms' => $version->warranty_terms,
                    'leadTimeDays' => $version->lead_time_days,
                ] : null,
                'award' => [
                    'id' => (string) $recommendation->id,
                    'rationale' => $recommendation->rationale,
                    'tradeoffSummary' => $recommendation->tradeoff_summary,
                    'riskSummary' => $recommendation->risk_summary,
                    'exceptionSummary' => $recommendation->exception_summary,
                ],
            ],
            'lines' => $version?->lineItems
                ->map(fn (QuotationVersionLineItem $line): array => [
                    'lineNumber' => $line->position,
                    'itemCode' => null,
                    'description' => $line->description,
                    'quantity' => $this->decimal($line->quantity, 4),
                    'unitOfMeasure' => $line->unit,
                    'unitPrice' => $this->decimal($line->unit_price, 2),
                    'taxAmount' => $this->decimal($line->tax_amount, 2),
                    'freightAmount' => null,
                    'discountAmount' => null,
                    'lineTotal' => $this->decimal($line->total_amount, 2),
                    'currency' => $version->currency,
                    'notes' => $line->notes,
                ])
                ->values()
                ->all() ?? [],
            'approval' => [
                'approvalInstanceId' => $approvalInstance !== null ? (string) $approvalInstance->id : null,
                'status' => $approvalInstance?->status?->value,
                'finalDecision' => $approvalInstance?->status === ApprovalInstanceStatus::Approved ? 'approved' : null,
                'approvedAt' => $recommendation->approved_at?->toISOString() ?? $approvalInstance?->completed_at?->toISOString(),
                'approvedBy' => $recommendation->approvedByUser?->name,
                'stages' => $approvalInstance?->stages
                    ->map(fn (ApprovalStage $stage): array => [
                        'stage' => $stage->name,
                        'sequence' => $stage->sequence,
                        'status' => $stage->status?->value,
                        'completedAt' => $stage->completed_at?->toISOString(),
                        'tasks' => $stage->tasks->map(fn ($task): array => [
                            'title' => $task->title,
                            'status' => $task->status?->value,
                            'decision' => $task->decision,
                            'assigneeName' => $task->assignee?->name,
                            'decidedByName' => $task->decidedBy?->name,
                            'decidedAt' => $task->decided_at?->toISOString(),
                        ])->values()->all(),
                    ])
                    ->values()
                    ->all() ?? [],
            ],
            'evidence' => $recommendation->evidenceReferences
                ->map(fn ($evidence): array => [
                    'type' => $evidence->evidence_type?->value ?? (string) $evidence->evidence_type,
                    'id' => (string) $evidence->evidence_id,
                    'label' => $evidence->label,
                ])
                ->values()
                ->all(),
            'warnings' => $warnings,
        ];
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $scale, '.', '');
    }
}
