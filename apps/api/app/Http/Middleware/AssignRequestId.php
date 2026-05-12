<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestIdFromHeader($request) ?? 'req_'.Str::uuid()->toString();
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function requestIdFromHeader(Request $request): ?string
    {
        $requestId = trim((string) $request->headers->get('X-Request-Id', ''));

        if ($requestId === '' || strlen($requestId) > 64) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]+$/', $requestId) === 1 ? $requestId : null;
    }
}
