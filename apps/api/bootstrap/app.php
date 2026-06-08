<?php

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiErrorResponse;
use App\Http\Middleware\AssignRequestId;
use App\Tenancy\AmbiguousTenantException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*') ? null : route('login')
        );
        $middleware->api(prepend: [AssignRequestId::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                $request,
                ApiErrorCode::ValidationFailed,
                'The given data was invalid.',
                422,
                ['fields' => $exception->errors()],
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::Unauthenticated, 'Authentication is required.', 401);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::Forbidden, 'You are not allowed to perform this action.', 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::NotFound, 'The requested resource was not found.', 404);
        });

        $exceptions->render(function (ConflictHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::Conflict, $exception->getMessage(), 409);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                $request,
                ApiErrorCode::TooManyRequests,
                'Too many requests.',
                429,
                [],
                $exception->getHeaders(),
            );
        });

        $exceptions->render(function (AmbiguousTenantException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::AmbiguousTenant, $exception->getMessage(), 400);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = ApiErrorCode::forHttpStatus($status);

            if ($code !== ApiErrorCode::ServerError) {
                return ApiErrorResponse::make(
                    $request,
                    $code,
                    $exception->getMessage() ?: 'An error occurred.',
                    $status,
                    [],
                    $exception->getHeaders(),
                );
            }

            return ApiErrorResponse::make(
                $request,
                $code,
                'An unexpected server error occurred.',
                $status,
                [],
                $exception->getHeaders(),
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::ServerError, 'An unexpected server error occurred.', 500);
        });
    })->create();
