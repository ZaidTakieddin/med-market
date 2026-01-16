<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\CleanupTransactions::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\CleanupTransactions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*');
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthenticated.',
                    'message' => 'Authentication required. Please provide a valid token.',
                ], 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (str_contains($e->getMessage(), 'login')) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthenticated.',
                        'message' => 'Authentication required. Please provide a valid token.',
                    ], 401);
                }

                return response()->json([
                    'success' => false,
                    'error' => 'Route not found.',
                    'message' => $e->getMessage(),
                ], 404);
            }
        });
    })->create();
