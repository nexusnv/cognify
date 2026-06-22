<?php

namespace Domains\Quotation\Data;

class QuotationCompletenessData
{
    /**
     * @param  array<int, string>  $missingFields
     */
    public function __construct(
        public readonly bool $isComplete,
        public readonly array $missingFields,
        public readonly int $lineItemCount,
    ) {}

    /**
     * @return array{isComplete: bool, missingFields: array<int, string>, lineItemCount: int}
     */
    public function toArray(): array
    {
        return [
            'isComplete' => $this->isComplete,
            'missingFields' => $this->missingFields,
            'lineItemCount' => $this->lineItemCount,
        ];
    }
}
