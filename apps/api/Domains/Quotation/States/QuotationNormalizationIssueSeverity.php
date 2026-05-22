<?php

namespace Domains\Quotation\States;

enum QuotationNormalizationIssueSeverity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
    case Info = 'info';
}
