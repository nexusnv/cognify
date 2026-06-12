<?php

namespace Domains\Invoice\Support;

class SupplierInvoiceNumber
{
    public static function normalize(string $invoiceNumber): string
    {
        $normalized = strtoupper(trim($invoiceNumber));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

        return $normalized === null || $normalized === ''
            ? strtoupper(trim($invoiceNumber))
            : $normalized;
    }
}
