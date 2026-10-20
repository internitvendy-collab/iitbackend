<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

        error_log(sprintf(
            '[%s] %s %s %d %.2f ms',
            now('UTC')->format('Y-m-d H:i:s T'),
            $request->method(),
            '/' . ltrim($request->path(), '/'),
            $response->getStatusCode(),
            $durationMs,
        ));

        return $response;
    }
}
