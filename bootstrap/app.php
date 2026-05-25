<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies so ngrok HTTPS headers are respected
        $middleware->trustProxies(
            at: '*',
            headers: \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR |
                     \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST |
                     \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT |
                     \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO
        );

        // Bypass ngrok browser-warning interstitial on every response.
        // Without this header, ngrok's free-tier CDN injects a "Visit Site" click-
        // through page that breaks first-load on browsers and all mobile access.
        $middleware->append(\App\Http\Middleware\NgrokHeaders::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // All JSON-expecting requests (API calls and AJAX from the UI) get a
        // consistent {status, message, data} envelope — never a raw stack trace.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null; // let Laravel render the HTML error page normally
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation failed.',
                    'data'    => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthenticated.',
                    'data'    => null,
                ], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized.',
                    'data'    => null,
                ], 403);
            }

            $httpStatus = $e instanceof HttpException ? $e->getStatusCode() : 500;

            // Hide internal details in production
            $message = ($httpStatus === 500 && app()->environment('production'))
                ? 'An unexpected error occurred. Please try again.'
                : $e->getMessage();

            return response()->json([
                'status'  => 'error',
                'message' => $message,
                'data'    => null,
            ], $httpStatus);
        });

    })->create();
