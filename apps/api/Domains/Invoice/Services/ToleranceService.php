<?php

namespace Domains\Invoice\Services;

use Domains\Invoice\Data\MatchingToleranceConfigData;

class ToleranceService
{
    private ?array $tenantConfig;

    public function __construct(?array $tenantConfig = null)
    {
        $this->tenantConfig = $tenantConfig;
    }

    public function compare(
        string $expected,
        string $actual,
        string $dimension,
    ): array {
        $tolerance = MatchingToleranceConfigData::forDimension($dimension, $this->tenantConfig);

        if (bccomp($expected, '0', 4) === 0 && bccomp($actual, '0', 4) === 0) {
            return $this->passResult($tolerance);
        }

        if (bccomp($expected, '0', 4) === 0 && bccomp($actual, '0', 4) !== 0) {
            return $this->failResult($tolerance, 'Expected value is zero but actual is non-zero.');
        }

        $diff = bcsub($expected, $actual, 4);
        $variance = bccomp($diff, '0', 4) < 0 ? bcsub('0', $diff, 4) : $diff;

        $percentageTolerance = bcmul($expected, (string) ($tolerance['percent'] / 100), 4);
        $effectiveTolerance = bccomp($percentageTolerance, (string) $tolerance['floor'], 4) >= 0
            ? $percentageTolerance
            : (string) $tolerance['floor'];

        $passesEffective = bccomp($variance, $effectiveTolerance, 4) <= 0;
        $passesCap = $tolerance['cap'] == 0 || bccomp($variance, (string) $tolerance['cap'], 4) <= 0;

        if ($passesEffective && $passesCap) {
            return $this->passResult($tolerance);
        }

        $notes = [];
        if (! $passesEffective) {
            $notes[] = sprintf('Variance %s exceeds effective tolerance %s', $variance, $effectiveTolerance);
        }
        if (! $passesCap) {
            $notes[] = sprintf('Variance %s exceeds hard cap %s', $variance, $tolerance['cap']);
        }

        return $this->failResult($tolerance, implode('; ', $notes));
    }

    public function compareQuantity(
        string $cumulativeInvoiced,
        string $currentInvoiceQty,
        string $effectivePoQty,
    ): array {
        $totalInvoiced = bcadd($cumulativeInvoiced, $currentInvoiceQty, 4);

        if (bccomp($totalInvoiced, $effectivePoQty, 4) <= 0) {
            return [
                'result' => 'pass',
                'notes' => null,
            ];
        }

        $excess = bcsub($totalInvoiced, $effectivePoQty, 4);

        return [
            'result' => 'fail',
            'notes' => sprintf(
                'Cumulative invoiced quantity %s exceeds PO quantity %s by %s',
                $totalInvoiced,
                $effectivePoQty,
                $excess,
            ),
        ];
    }

    private function passResult(array $tolerance): array
    {
        return [
            'result' => 'pass',
            'tolerance_percent_applied' => $tolerance['percent'],
            'tolerance_floor_applied' => $tolerance['floor'],
            'tolerance_cap_applied' => $tolerance['cap'],
            'notes' => null,
        ];
    }

    private function failResult(array $tolerance, string $notes): array
    {
        return [
            'result' => 'fail',
            'tolerance_percent_applied' => $tolerance['percent'],
            'tolerance_floor_applied' => $tolerance['floor'],
            'tolerance_cap_applied' => $tolerance['cap'],
            'notes' => $notes,
        ];
    }
}
