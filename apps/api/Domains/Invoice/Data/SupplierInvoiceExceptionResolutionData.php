<?php

namespace Domains\Invoice\Data;

use InvalidArgumentException;

class SupplierInvoiceExceptionResolutionData
{
    public const RESOLUTION_TYPES = ['value_adjustment', 'explanation'];

    public static function normalize(string $resolutionType, ?string $adjustedValue, ?string $explanation): array
    {
        if (! in_array($resolutionType, self::RESOLUTION_TYPES, true)) {
            throw new InvalidArgumentException('Resolution type must be value_adjustment or explanation.');
        }

        $data = [];

        if ($resolutionType === 'value_adjustment') {
            if ($adjustedValue === null || ! is_numeric($adjustedValue)) {
                throw new InvalidArgumentException('Adjusted value is required for value_adjustment resolution.');
            }
            $data['adjusted_value'] = $adjustedValue;
        }

        if ($explanation !== null && trim($explanation) !== '') {
            $data['explanation'] = trim($explanation);
        }

        return $data;
    }
}
