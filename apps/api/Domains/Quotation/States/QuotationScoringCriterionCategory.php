<?php

namespace Domains\Quotation\States;

enum QuotationScoringCriterionCategory: string
{
    case Cost = 'cost';
    case Delivery = 'delivery';
    case Quality = 'quality';
    case Compliance = 'compliance';
    case Risk = 'risk';
    case Sustainability = 'sustainability';
    case PastPerformance = 'past_performance';
    case Other = 'other';
}
