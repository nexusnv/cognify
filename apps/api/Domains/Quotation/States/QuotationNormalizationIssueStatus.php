<?php

namespace Domains\Quotation\States;

enum QuotationNormalizationIssueStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Acknowledged = 'acknowledged';
}
