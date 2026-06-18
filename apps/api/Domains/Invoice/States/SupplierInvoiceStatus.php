<?php

namespace Domains\Invoice\States;

enum SupplierInvoiceStatus: string
{
    case Captured = 'captured';
    case InReview = 'in_review';
    case NeedsInformation = 'needs_information';
    case Reviewed = 'reviewed';
    case Matched = 'matched';
    case Mismatch = 'mismatch';
    case ReadyForApproval = 'ready_for_approval';
    case InApproval = 'in_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
}
