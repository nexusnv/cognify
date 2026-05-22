<?php

namespace Domains\Quotation\States;

enum QuotationComparisonNoteSection: string
{
    case Overall = 'overall';
    case Price = 'price';
    case Delivery = 'delivery';
    case Terms = 'terms';
    case Compliance = 'compliance';
    case Risk = 'risk';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $section): string => $section->value, self::cases());
    }
}
