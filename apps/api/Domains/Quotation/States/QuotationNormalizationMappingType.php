<?php

namespace Domains\Quotation\States;

enum QuotationNormalizationMappingType: string
{
    case Full = 'full';
    case Partial = 'partial';
    case Bundled = 'bundled';
}
