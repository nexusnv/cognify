<?php

namespace Domains\Invoice\States;

enum SupplierInvoiceStatus: string
{
    case Captured = 'captured';
}
