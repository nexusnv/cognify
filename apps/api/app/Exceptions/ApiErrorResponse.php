<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string|array<int, string>>  $headers
     */
    public static function make(
        Request $request,
        ApiErrorCode $code,
        string $message,
        int $status,
        array $details = [],
        array $headers = [],
    ): JsonResponse {
        $requestId = $request->attributes->get('request_id') ?? 'req_'.Str::uuid()->toString();

        return response()
            ->json([
                'error' => [
                    'code' => $code->value,
                    'message' => $message,
                    'details' => (object) $details,
                    'requestId' => $requestId,
                ],
            ], $status, $headers)
            ->withHeaders(['X-Request-Id' => $requestId]);
    }
}
