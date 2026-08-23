<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin.ip' => \App\Http\Middleware\AdminIpAllowlist::class,
        ]);

        // Enable CORS for API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CleanupTransactions::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\CleanupTransactions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API routes return JSON responses
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Every API/JSON error path renders the standardized nested body
        // ({ success:false, error:{title,message}, errors? }) regardless of which exception
        // type Laravel raises. Controllers do NOT catch these — handling stays here so status
        // codes and shape stay consistent (404 for missing models, 403 for policy denials, etc.).
        $wantsJson = fn ($request): bool => $request->is('api/*') || $request->expectsJson();

        $json = function (string $title, string $message, int $status, ?array $errors = null): \Illuminate\Http\JsonResponse {
            $body = [
                'success' => false,
                'error' => ['title' => $title, 'message' => $message],
            ];
            if ($errors !== null) {
                $body['errors'] = $errors;
            }

            return response()->json($body, $status);
        };

        // Domain business-rule violations (thrown by domain services).
        $exceptions->render(function (\App\Exceptions\DomainException $e, $request) use ($wantsJson, $json) {
            return $wantsJson($request) ? $json('Unprocessable', $e->getMessage(), 422) : null;
        });

        // Validation thrown outside a Form Request (manual validators).
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) use ($wantsJson, $json) {
            return $wantsJson($request) ? $json('Validation failed', 'The given data was invalid.', 422, $e->errors()) : null;
        });

        // Unauthenticated (missing/invalid Sanctum token). Not converted by prepareException(),
        // so it must be handled here rather than via the HTTP catch-all below.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) use ($wantsJson, $json) {
            return $wantsJson($request) ? $json('Unauthenticated', 'Authentication required.', 401) : null;
        });

        // Catch-all for HTTP exceptions, preserving response headers such as throttle Retry-After.
        // By the time render callbacks run, Laravel's prepareException() has already mapped
        // ModelNotFoundException -> NotFoundHttpException (404) and AuthorizationException ->
        // AccessDeniedHttpException (403), so this single handler covers policy denials, missing
        // models, route misses, abort(), 405, 429, etc. Messages are generic per status — never
        // $e->getMessage() — to avoid leaking internals (e.g. the model class behind a 404).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) use ($wantsJson, $json) {
            if (! $wantsJson($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $title = match ($status) {
                400 => 'Bad request',
                401 => 'Unauthenticated',
                403 => 'Forbidden',
                404 => 'Not found',
                405 => 'Method not allowed',
                409 => 'Conflict',
                422 => 'Unprocessable',
                429 => 'Too many requests',
                503 => 'Service unavailable',
                default => 'Error',
            };
            $message = match ($status) {
                400 => 'The request was invalid.',
                401 => 'Authentication required.',
                403 => 'You do not have permission to perform this action.',
                404 => 'Resource not found.',
                405 => 'The method is not allowed for this route.',
                409 => 'The request conflicts with the current state of the resource.',
                422 => 'The given data was invalid.',
                429 => 'Too many requests. Please slow down.',
                503 => 'The service is temporarily unavailable.',
                default => 'An unexpected error occurred.',
            };
            $response = $json($title, $message, $status);
            foreach ($e->getHeaders() as $key => $value) {
                $response->headers->set($key, $value);
            }

            return $response;
        });
    })->create();
