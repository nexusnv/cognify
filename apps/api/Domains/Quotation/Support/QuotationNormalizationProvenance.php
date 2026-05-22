<?php

namespace Domains\Quotation\Support;

use Domains\Quotation\Models\QuotationVersion;

final class QuotationNormalizationProvenance
{
    /**
     * @param  array<string, mixed>|null  $rawValue
     * @param  array<string, mixed>|null  $normalizedValue
     * @return array<string, mixed>
     */
    public static function field(
        QuotationVersion $version,
        string $fieldPath,
        mixed $rawValue,
        mixed $normalizedValue,
        string $algorithmVersion,
        string $source = 'quotation_version',
    ): array {
        return [
            'sourceQuotationVersionId' => (string) $version->id,
            'sourceFieldPath' => $fieldPath,
            'rawValue' => $rawValue,
            'normalizedValue' => $normalizedValue,
            'algorithmVersion' => $algorithmVersion,
            'source' => $source,
        ];
    }
}
