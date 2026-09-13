<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Required for Sanctum's SPA cookie authentication: requests from the
        // Next.js frontend origin are treated as "stateful" (session/cookie based)
        // rather than needing a bearer token.
        $middleware->statefulApi();
        $middleware->appendToGroup('api', [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            // no exceptions - CSRF protection stays on for all stateful routes
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Consistent JSON error shape for the API (spec section 41), and never
        // leak stack traces to the frontend outside local/debug environments.
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });
    })->create();
