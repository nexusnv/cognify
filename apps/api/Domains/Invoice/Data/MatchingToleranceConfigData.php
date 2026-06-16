<?php

namespace Domains\Invoice\Data;

final class MatchingToleranceConfigData
{
    private const DEFAULTS = [
        'unit_price' => ['percent' => 5.0, 'floor' => 2.00, 'cap' => 250.00],
        'line_total' => ['percent' => 5.0, 'floor' => 10.00, 'cap' => 500.00],
        'tax' => ['percent' => 2.0, 'floor' => 5.00, 'cap' => 100.00],
        'freight' => ['percent' => 5.0, 'floor' => 5.00, 'cap' => 100.00],
        'invoice_total' => ['percent' => 2.0, 'floor' => 25.00, 'cap' => 1000.00],
        'quantity_over' => ['percent' => 0.0, 'floor' => 0.0, 'cap' => 0.0],
    ];

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public static function forDimension(string $dimension, ?array $tenantConfig = null): array
    {
        $default = self::DEFAULTS[$dimension] ?? self::DEFAULTS['unit_price'];

        if ($tenantConfig === null || ! isset($tenantConfig[$dimension])) {
            return $default;
        }

        $tenantDim = $tenantConfig[$dimension];

        return [
            'percent' => (float) ($tenantDim['percent'] ?? $default['percent']),
            'floor' => (float) ($tenantDim['floor'] ?? $default['floor']),
            'cap' => (float) ($tenantDim['cap'] ?? $default['cap']),
        ];
    }
}
