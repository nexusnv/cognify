<?php

namespace Domains\Invoice\Data;

use InvalidArgumentException;

class SupplierInvoiceReviewChecklistData
{
    public const KEYS = ['completeness', 'coding', 'attachment', 'vendorIdentity', 'poLinkage'];

    public const STATUSES = ['pass', 'fail', 'needs_attention'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{status: string, note: ?string}>
     */
    public static function normalize(array $payload): array
    {
        $normalized = [];

        foreach (self::KEYS as $key) {
            $item = $payload[$key] ?? null;

            if (! is_array($item)) {
                throw new InvalidArgumentException("Checklist item {$key} is required.");
            }

            $status = $item['status'] ?? null;

            if (! in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException("Checklist item {$key} has an invalid status.");
            }

            $note = $item['note'] ?? null;

            $normalized[$key] = [
                'status' => $status,
                'note' => is_string($note) && trim($note) !== '' ? trim($note) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array{status?: mixed, note?: mixed}>  $checklist
     */
    public static function allPassed(array $checklist): bool
    {
        foreach (self::KEYS as $key) {
            if (($checklist[$key]['status'] ?? null) !== 'pass') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array{status?: mixed, note?: mixed}>  $checklist
     * @return array<int, array{key: string, status: string, note: ?string}>
     */
    public static function blockers(array $checklist): array
    {
        $blockers = [];

        foreach (self::KEYS as $key) {
            $status = $checklist[$key]['status'] ?? null;

            if (! in_array($status, ['fail', 'needs_attention'], true)) {
                continue;
            }

            $blockers[] = [
                'key' => $key,
                'status' => $status,
                'note' => isset($checklist[$key]['note']) && is_string($checklist[$key]['note']) && trim($checklist[$key]['note']) !== ''
                    ? trim($checklist[$key]['note'])
                    : null,
            ];
        }

        return $blockers;
    }

    /**
     * @param  array<string, array{status?: mixed, note?: mixed}>|null  $checklist
     * @return array{total: int, passed: int, needsAttention: int, failed: int}
     */
    public static function summary(?array $checklist): array
    {
        if ($checklist === null) {
            return [
                'total' => count(self::KEYS),
                'passed' => 0,
                'needsAttention' => 0,
                'failed' => 0,
            ];
        }

        $passed = 0;
        $needsAttention = 0;
        $failed = 0;

        foreach (self::KEYS as $key) {
            $status = $checklist[$key]['status'] ?? null;

            if ($status === 'pass') {
                $passed++;
            }

            if ($status === 'needs_attention') {
                $needsAttention++;
            }

            if ($status === 'fail') {
                $failed++;
            }
        }

        return [
            'total' => count(self::KEYS),
            'passed' => $passed,
            'needsAttention' => $needsAttention,
            'failed' => $failed,
        ];
    }
}
