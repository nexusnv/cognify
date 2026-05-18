<?php

namespace Domains\Approval\Data;

use Domains\Requisition\Models\Requisition;

final class ApprovalContextData
{
    /**
     * @param array<int, string> $lineItemCategories
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly ?string $requisitionId,
        public readonly ?string $requesterId,
        public readonly float $amount,
        public readonly ?string $currency,
        public readonly ?string $department,
        public readonly ?string $costCenter,
        public readonly ?string $projectId,
        public readonly array $lineItemCategories,
        public readonly ?string $riskClassification,
        public readonly ?string $vendorId,
    ) {
    }

    public static function fromRequisition(Requisition $requisition): self
    {
        $requisition->loadMissing('lineItems');

        return new self(
            tenantId: (string) $requisition->tenant_id,
            requisitionId: (string) $requisition->id,
            requesterId: (string) $requisition->requester_id,
            amount: round(
                $requisition->lineItems->reduce(
                    fn (float $carry, $lineItem): float => $carry + ((float) $lineItem->quantity * (float) $lineItem->estimated_unit_price),
                    0.0,
                ),
                2,
            ),
            currency: $requisition->currency,
            department: $requisition->department,
            costCenter: $requisition->cost_center,
            projectId: $requisition->project_id !== null ? (string) $requisition->project_id : null,
            lineItemCategories: self::lineItemCategories($requisition),
            riskClassification: data_get($requisition, 'risk_classification') !== null
                ? (string) data_get($requisition, 'risk_classification')
                : null,
            vendorId: data_get($requisition, 'vendor_id') !== null ? (string) data_get($requisition, 'vendor_id') : null,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function fromArray(string $tenantId, array $context): self
    {
        $lineItemCategories = array_values(array_filter(array_map(
            static fn ($category): string => trim((string) $category),
            (array) ($context['lineItemCategories'] ?? []),
        )));

        return new self(
            tenantId: $tenantId,
            requisitionId: isset($context['requisitionId']) ? (string) $context['requisitionId'] : null,
            requesterId: isset($context['requesterId']) ? (string) $context['requesterId'] : null,
            amount: isset($context['amount']) ? round((float) $context['amount'], 2) : 0.0,
            currency: isset($context['currency']) && $context['currency'] !== '' ? (string) $context['currency'] : null,
            department: isset($context['department']) && $context['department'] !== '' ? (string) $context['department'] : null,
            costCenter: isset($context['costCenter']) && $context['costCenter'] !== '' ? (string) $context['costCenter'] : null,
            projectId: isset($context['projectId']) && $context['projectId'] !== '' ? (string) $context['projectId'] : null,
            lineItemCategories: $lineItemCategories,
            riskClassification: isset($context['riskClassification']) && $context['riskClassification'] !== ''
                ? (string) $context['riskClassification']
                : null,
            vendorId: isset($context['vendorId']) && $context['vendorId'] !== '' ? (string) $context['vendorId'] : null,
        );
    }

    /**
     * @return array<int, string>
     */
    public function missingRequiredContext(): array
    {
        $missing = [];

        if ($this->riskClassification === null) {
            $missing[] = 'riskClassification';
        }

        if ($this->vendorId === null) {
            $missing[] = 'vendorId';
        }

        return $missing;
    }

    /**
     * @return array<int, string>
     */
    private static function lineItemCategories(Requisition $requisition): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($lineItem): string => trim((string) $lineItem->name),
            $requisition->lineItems->all(),
        ))));
    }
}
