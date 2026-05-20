<?php

namespace Domains\Quotation\States;

enum QuotationSubmissionSource: string
{
    case VendorPortal = 'vendor_portal';
    case BuyerUpload = 'buyer_upload';
}
