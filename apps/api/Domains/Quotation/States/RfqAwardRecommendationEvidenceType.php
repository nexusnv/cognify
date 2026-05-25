<?php

namespace Domains\Quotation\States;

enum RfqAwardRecommendationEvidenceType: string
{
    case QuotationVersion = 'quotation_version';
    case QuotationAttachment = 'quotation_attachment';
    case ComparisonNote = 'comparison_note';
    case Scorecard = 'scorecard';
}
