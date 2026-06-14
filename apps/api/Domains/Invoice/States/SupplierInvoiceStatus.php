<?php

namespace Domains\Invoice\States;

enum SupplierInvoiceStatus: string
{
    case Captured = 'captured';
    case InReview = 'in_review';
    case NeedsInformation = 'needs_information';
    case Reviewed = 'reviewed';
}
