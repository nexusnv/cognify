<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoExceptionType: string
{
    case MissingInvoiceReference = 'missing_invoice_reference';
    case OverCredit = 'over_credit';
    case VendorMismatch = 'vendor_mismatch';
    case TaxCodeMismatch = 'tax_code_mismatch';
    case MathError = 'math_error';
    case DuplicateCredit = 'duplicate_credit';
    case MissingTaxCode = 'missing_tax_code';
    case CurrencyMismatch = 'currency_mismatch';

    public function label(): string
    {
        return match ($this) {
            self::MissingInvoiceReference => 'Missing invoice reference',
            self::OverCredit => 'Over-credit',
            self::VendorMismatch => 'Vendor mismatch',
            self::TaxCodeMismatch => 'Tax code mismatch',
            self::MathError => 'Math error',
            self::DuplicateCredit => 'Duplicate credit',
            self::MissingTaxCode => 'Missing tax code',
            self::CurrencyMismatch => 'Currency mismatch',
        };
    }
}
