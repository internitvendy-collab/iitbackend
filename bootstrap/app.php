<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(App\Http\Middleware\RequestLoggingMiddleware::class);
        $middleware->alias([
            'api.token' => App\Http\Middleware\ApiTokenMiddleware::class,
            'role' => App\Http\Middleware\RoleMiddleware::class,
            'permission' => App\Http\Middleware\PermissionMiddleware::class,
            'request.user' => App\Http\Middleware\RequestUserMatchesAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Validation failed.', 400, $exception->errors());
            }
        });

        $exceptions->renderable(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->renderable(function (AuthorizationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Forbidden.', 403);
            }
        });

        $exceptions->renderable(function (HttpExceptionInterface $exception, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $exception->getMessage();

            if ($message === '' || $message === null) {
                $message = Response::$statusTexts[$status] ?? 'Request failed.';
            }

            return ApiResponse::error($message, $status);
        });

        // Never leak stack traces, SQL or environment details from API routes.
        $exceptions->renderable(function (Throwable $exception, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            report($exception);

            return ApiResponse::error('Something went wrong. Please try again later.', 500);
        });
    })
    ->create();