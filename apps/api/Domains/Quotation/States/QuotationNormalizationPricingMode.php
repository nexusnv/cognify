<?php

namespace Domains\Quotation\States;

enum QuotationNormalizationPricingMode: string
{
    case PerLine = 'per_line';
    case Bundle = 'bundle';
    case Included = 'included';
    case Unknown = 'unknown';
}
