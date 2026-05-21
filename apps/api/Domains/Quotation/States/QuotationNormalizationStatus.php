<?php

namespace Domains\Quotation\States;

enum QuotationNormalizationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case ReadyForApproval = 'ready_for_approval';
    case Approved = 'approved';
    case ApprovedWithWarnings = 'approved_with_warnings';
    case Failed = 'failed';
    case Superseded = 'superseded';
}
