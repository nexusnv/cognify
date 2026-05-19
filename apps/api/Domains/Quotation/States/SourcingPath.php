<?php

namespace Domains\Quotation\States;

enum SourcingPath: string
{
    case NeedsRfq = 'needs_rfq';
    case NeedsClarification = 'needs_clarification';
    case DirectAward = 'direct_award';
    case NoSourcingRequired = 'no_sourcing_required';
}
