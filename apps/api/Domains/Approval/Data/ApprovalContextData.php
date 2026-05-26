<?php

namespace Domains\Approval\Data;

use Domains\Requisition\Models\Requisition;
use InvalidArgumentException;

final class ApprovalContextData
{
    /**
     * @param  array<int, string>  $lineItemCategories
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $subjectType,
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
        public readonly ?string $awardRecommendationId = null,
        public readonly ?string $rfqId = null,
        public readonly ?string $rfqNumber = null,
        public readonly ?string $recommendedVendorId = null,
        public readonly ?string $recommendedVendorName = null,
        public readonly ?string $recommendedQuotationId = null,
        public readonly ?string $recommendedQuotationVersionId = null,
        public readonly ?float $recommendedAmount = null,
        public readonly ?string $recommendedCurrency = null,
        public readonly ?string $scorecardId = null,
        public readonly ?float $scorecardWeightedTotal = null,
        public readonly bool $riskSummaryPresent = false,
        public readonly bool $exceptionSummaryPresent = false,
    ) {}

    public static function fromRequisition(Requisition $requisition): self
    {
        $requisition->loadMissing('lineItems');

        return new self(
            tenantId: (string) $requisition->tenant_id,
            subjectType: 'requisition',
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
     * @param  array<string, mixed>  $context
     */
    public static function fromArray(string $tenantId, array $context): self
    {
        $lineItemCategories = array_values(array_filter(array_map(
            static fn ($category): string => trim((string) $category),
            (array) ($context['lineItemCategories'] ?? []),
        )));

        return new self(
            tenantId: $tenantId,
            subjectType: isset($context['subjectType']) && $context['subjectType'] !== '' ? (string) $context['subjectType'] : 'requisition',
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
            awardRecommendationId: isset($context['awardRecommendationId']) && $context['awardRecommendationId'] !== '' ? (string) $context['awardRecommendationId'] : null,
            rfqId: isset($context['rfqId']) && $context['rfqId'] !== '' ? (string) $context['rfqId'] : null,
            rfqNumber: isset($context['rfqNumber']) && $context['rfqNumber'] !== '' ? (string) $context['rfqNumber'] : null,
            recommendedVendorId: isset($context['recommendedVendorId']) && $context['recommendedVendorId'] !== '' ? (string) $context['recommendedVendorId'] : null,
            recommendedVendorName: isset($context['recommendedVendorName']) && $context['recommendedVendorName'] !== '' ? (string) $context['recommendedVendorName'] : null,
            recommendedQuotationId: isset($context['recommendedQuotationId']) && $context['recommendedQuotationId'] !== '' ? (string) $context['recommendedQuotationId'] : null,
            recommendedQuotationVersionId: isset($context['recommendedQuotationVersionId']) && $context['recommendedQuotationVersionId'] !== '' ? (string) $context['recommendedQuotationVersionId'] : null,
            recommendedAmount: isset($context['recommendedAmount']) && $context['recommendedAmount'] !== '' && is_numeric($context['recommendedAmount'])
                ? round((float) $context['recommendedAmount'], 2)
                : null,
            recommendedCurrency: isset($context['recommendedCurrency']) && $context['recommendedCurrency'] !== '' ? (string) $context['recommendedCurrency'] : null,
            scorecardId: isset($context['scorecardId']) && $context['scorecardId'] !== '' ? (string) $context['scorecardId'] : null,
            scorecardWeightedTotal: isset($context['scorecardWeightedTotal']) && $context['scorecardWeightedTotal'] !== '' && is_numeric($context['scorecardWeightedTotal'])
                ? round((float) $context['scorecardWeightedTotal'], 2)
                : null,
            riskSummaryPresent: (bool) ($context['riskSummaryPresent'] ?? false),
            exceptionSummaryPresent: (bool) ($context['exceptionSummaryPresent'] ?? false),
        );
    }

    /**
     * @param  array<int, string>  $requiredFields
     * @return array<int, string>
     */
    public function missingRequiredContext(array $requiredFields = []): array
    {
        $missing = [];
        $supportedFields = array_flip([
            'tenantId',
            'subjectType',
            'requisitionId',
            'requesterId',
            'amount',
            'currency',
            'department',
            'costCenter',
            'projectId',
            'lineItemCategories',
            'riskClassification',
            'vendorId',
            'awardRecommendationId',
            'rfqId',
            'rfqNumber',
            'recommendedVendorId',
            'recommendedVendorName',
            'recommendedQuotationId',
            'recommendedQuotationVersionId',
            'recommendedAmount',
            'recommendedCurrency',
            'scorecardId',
            'scorecardWeightedTotal',
            'riskSummaryPresent',
            'exceptionSummaryPresent',
        ]);

        foreach (array_values(array_unique($requiredFields)) as $field) {
            if (! isset($supportedFields[$field])) {
                throw new InvalidArgumentException("Unsupported approval context field [{$field}].");
            }

            $value = $this->{$field};
            if ($value === null || $value === '') {
                $missing[] = $field;

                continue;
            }

            if (is_array($value) && $value === []) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenantId' => $this->tenantId,
            'subjectType' => $this->subjectType,
            'requisitionId' => $this->requisitionId,
            'requesterId' => $this->requesterId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'department' => $this->department,
            'costCenter' => $this->costCenter,
            'projectId' => $this->projectId,
            'lineItemCategories' => $this->lineItemCategories,
            'riskClassification' => $this->riskClassification,
            'vendorId' => $this->vendorId,
            'awardRecommendationId' => $this->awardRecommendationId,
            'rfqId' => $this->rfqId,
            'rfqNumber' => $this->rfqNumber,
            'recommendedVendorId' => $this->recommendedVendorId,
            'recommendedVendorName' => $this->recommendedVendorName,
            'recommendedQuotationId' => $this->recommendedQuotationId,
            'recommendedQuotationVersionId' => $this->recommendedQuotationVersionId,
            'recommendedAmount' => $this->recommendedAmount,
            'recommendedCurrency' => $this->recommendedCurrency,
            'scorecardId' => $this->scorecardId,
            'scorecardWeightedTotal' => $this->scorecardWeightedTotal,
            'riskSummaryPresent' => $this->riskSummaryPresent,
            'exceptionSummaryPresent' => $this->exceptionSummaryPresent,
        ];
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
