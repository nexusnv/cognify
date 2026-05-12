<?php

namespace App\Exceptions;

enum ApiErrorCode: string
{
    case ValidationFailed = 'validation_failed';
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case Conflict = 'conflict';
    case AmbiguousTenant = 'ambiguous_tenant';
    case TooManyRequests = 'too_many_requests';
    case ServerError = 'server_error';

    public static function forHttpStatus(int $status): self
    {
        return match ($status) {
            401 => self::Unauthenticated,
            403 => self::Forbidden,
            404 => self::NotFound,
            409 => self::Conflict,
            429 => self::TooManyRequests,
            default => self::ServerError,
        };
    }
}
