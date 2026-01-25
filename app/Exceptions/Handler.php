<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e): Response
    {
        // Handle API requests with JSON responses
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions and return standardized JSON responses.
     */
    protected function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        // Handle validation exceptions
        if ($e instanceof ValidationException) {
            return $this->handleValidationException($e);
        }

        // Handle authentication exceptions
        if ($e instanceof AuthenticationException) {
            return $this->handleAuthenticationException($e);
        }

        // Handle authorization exceptions
        if ($e instanceof AccessDeniedHttpException) {
            return $this->handleAuthorizationException($e);
        }

        // Handle model not found exceptions
        if ($e instanceof ModelNotFoundException) {
            return $this->handleModelNotFoundException($e);
        }

        // Handle not found exceptions
        if ($e instanceof NotFoundHttpException) {
            return $this->handleNotFoundException($e);
        }

        // Handle route not found
        if ($e instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) {
            return $this->handleRouteNotFoundException($e);
        }

        // Handle invalid argument exceptions (from domain services)
        if ($e instanceof \InvalidArgumentException) {
            return $this->handleInvalidArgumentException($e);
        }

        // Handle database query exceptions
        if ($e instanceof \Illuminate\Database\QueryException) {
            return $this->handleQueryException($e);
        }

        // Handle all other exceptions
        return $this->handleGenericException($e);
    }

    /**
     * Handle validation exceptions with improved error messages.
     */
    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        $errors = [];
        $messages = $e->errors();

        // Format validation errors with field names and messages
        foreach ($messages as $field => $fieldErrors) {
            $errors[$field] = is_array($fieldErrors) ? $fieldErrors : [$fieldErrors];
        }

        return response()->json([
            'success' => false,
            'error' => 'Validation failed',
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Handle authentication exceptions.
     */
    protected function handleAuthenticationException(AuthenticationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Unauthenticated',
            'message' => 'Authentication required. Please provide a valid token.',
        ], 401);
    }

    /**
     * Handle authorization exceptions.
     */
    protected function handleAuthorizationException(AccessDeniedHttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Forbidden',
            'message' => $e->getMessage() ?: 'You do not have permission to perform this action.',
        ], 403);
    }

    /**
     * Handle model not found exceptions.
     */
    protected function handleModelNotFoundException(ModelNotFoundException $e): JsonResponse
    {
        $model = class_basename($e->getModel());
        
        return response()->json([
            'success' => false,
            'error' => 'Resource not found',
            'message' => "{$model} not found.",
        ], 404);
    }

    /**
     * Handle not found exceptions.
     */
    protected function handleNotFoundException(NotFoundHttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Not found',
            'message' => $e->getMessage() ?: 'The requested resource was not found.',
        ], 404);
    }

    /**
     * Handle route not found exceptions.
     */
    protected function handleRouteNotFoundException(\Symfony\Component\Routing\Exception\RouteNotFoundException $e): JsonResponse
    {
        // Check if it's an authentication issue
        if (str_contains($e->getMessage(), 'login') || str_contains($e->getMessage(), 'auth')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
                'message' => 'Authentication required. Please provide a valid token.',
            ], 401);
        }

        return response()->json([
            'success' => false,
            'error' => 'Route not found',
            'message' => 'The requested route was not found.',
        ], 404);
    }

    /**
     * Handle invalid argument exceptions (from domain services).
     */
    protected function handleInvalidArgumentException(\InvalidArgumentException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Invalid request',
            'message' => $e->getMessage(),
        ], 400);
    }

    /**
     * Handle database query exceptions.
     */
    protected function handleQueryException(\Illuminate\Database\QueryException $e): JsonResponse
    {
        // Log the full error for debugging
        \Log::error('Database query exception', [
            'message' => $e->getMessage(),
            'sql' => $e->getSql(),
            'bindings' => $e->getBindings(),
        ]);

        // Check for specific error types
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        // Handle unique constraint violations
        if (str_contains($errorMessage, 'unique constraint') || 
            str_contains($errorMessage, 'duplicate key') ||
            $errorCode === '23505') {
            return response()->json([
                'success' => false,
                'error' => 'Duplicate entry',
                'message' => 'A record with this information already exists.',
            ], 409);
        }

        // Handle foreign key constraint violations
        if (str_contains($errorMessage, 'foreign key constraint') ||
            str_contains($errorMessage, 'violates foreign key') ||
            $errorCode === '23503') {
            return response()->json([
                'success' => false,
                'error' => 'Invalid reference',
                'message' => 'The operation violates a foreign key constraint. Please check related records.',
            ], 422);
        }

        // Handle transaction errors
        if (str_contains($errorMessage, 'transaction') && str_contains($errorMessage, 'aborted')) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction error',
                'message' => 'A database transaction error occurred. Please try again.',
            ], 500);
        }

        // Generic database error
        return response()->json([
            'success' => false,
            'error' => 'Database error',
            'message' => config('app.debug') 
                ? $e->getMessage() 
                : 'A database error occurred. Please try again later.',
        ], 500);
    }

    /**
     * Handle generic exceptions.
     */
    protected function handleGenericException(Throwable $e): JsonResponse
    {
        // Log the exception
        \Log::error('Unhandled exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $statusCode = method_exists($e, 'getStatusCode') 
            ? $e->getStatusCode() 
            : 500;

        return response()->json([
            'success' => false,
            'error' => 'Server error',
            'message' => config('app.debug') 
                ? $e->getMessage() 
                : 'An unexpected error occurred. Please try again later.',
            ...(config('app.debug') ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ] : []),
        ], $statusCode);
    }
}

