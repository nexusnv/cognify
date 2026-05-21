<?php

namespace Domains\Quotation\Support;

final class QuotationNormalizationIssueCatalog
{
    public const MISSING_CURRENCY = 'missing_currency';
    public const INVALID_CURRENCY = 'invalid_currency';
    public const MISSING_TOTAL_AMOUNT = 'missing_total_amount';
    public const MISSING_COMPARABLE_LINE_ITEMS = 'missing_comparable_line_items';
    public const REQUIRED_RFQ_LINE_UNMAPPED = 'required_rfq_line_unmapped';
    public const TOTAL_RECONCILIATION_MISMATCH = 'total_reconciliation_mismatch';
    public const PAYMENT_TERMS_UNSTRUCTURED = 'payment_terms_unstructured';
    public const WARRANTY_TERMS_MISSING = 'warranty_terms_missing';
    public const ATTACHMENT_CHECKSUM_UNAVAILABLE = 'attachment_checksum_unavailable';
}
