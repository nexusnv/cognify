<?php

namespace Domains\Invoice\Support;

use App\Tenancy\Tenant;
use Domains\Invoice\Models\SupplierInvoice;

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

    public static function nextForTenant(int|string $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "INV-{$year}-";

        Tenant::query()
            ->whereKey($tenantId)
            ->lockForUpdate()
            ->firstOrFail();

        $latestNumber = SupplierInvoice::query()
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('number')
            ->all();

        $sequence = 1;

        foreach ($latestNumber as $number) {
            if (is_string($number) && preg_match('/^INV-\d{4}-(\d+)$/', $number, $matches) === 1) {
                $sequence = max($sequence, ((int) $matches[1]) + 1);
            }
        }

        return sprintf('%s%06d', $prefix, $sequence);
    }
}
