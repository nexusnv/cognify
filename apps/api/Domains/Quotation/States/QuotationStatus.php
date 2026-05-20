<?php

namespace Domains\Quotation\States;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';
    case submitted = 'submitted';
}
