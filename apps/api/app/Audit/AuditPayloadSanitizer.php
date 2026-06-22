<?php

namespace App\Audit;

class AuditPayloadSanitizer
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEY_PARTS = [
        'access_token',
        'api_key',
        'apikey',
        'authorization',
        'card_number',
        'cvv',
        'password',
        'passwd',
        'secret',
        'ssn',
        'token',
        'refresh_token',
    ];

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return self::sanitizeArray($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function sanitizeArray(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = is_array($value) ? self::sanitizeArray($value) : $value;
        }

        return $sanitized;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
