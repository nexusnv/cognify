<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderChangeOrderType: string
{
    case Amendment = 'amendment';
    case PartialCancellation = 'partial_cancellation';
    case FullCancellation = 'full_cancellation';
}
