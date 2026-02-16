<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    /**
     * Check if the request wants unpaginated results.
     * Use per_page=all or paginate=false to get all results.
     */
    protected function wantsUnpaginated(Request $request): bool
    {
        $perPage = $request->get('per_page');
        $paginate = $request->get('paginate');

        return $perPage === 'all' || $paginate === 'false' || $paginate === false;
    }

    /**
     * Apply pagination or return all results based on request.
     * Use per_page=all or paginate=false to get all results without pagination.
     * Returns [items, pagination|null] - pagination is null when unpaginated.
     *
     * @param Builder $query
     * @param Request $request
     * @param int $defaultPerPage
     * @return array{0: \Illuminate\Support\Collection, 1: array|null}
     */
    protected function paginateOrAll(Builder $query, Request $request, int $defaultPerPage = 15): array
    {
        if ($this->wantsUnpaginated($request)) {
            return [$query->get(), null];
        }

        $perPage = (int) $request->get('per_page', $defaultPerPage);
        $perPage = min(max($perPage, 1), 100); // Clamp between 1 and 100

        $paginator = $query->paginate($perPage);
        $items = $paginator->getCollection();

        return [$items, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ]];
    }
    /**
     * Return a successful JSON response.
     */
    protected function success($data = null, string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response.
     * 
     * @param string $message Error message
     * @param array|string|null $errors Validation errors or additional error details
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    protected function error(string $message, $errors = null, int $statusCode = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => $this->getErrorTitle($statusCode),
            'message' => $message,
        ];

        // Handle validation errors (array format)
        if ($errors !== null) {
            if (is_array($errors)) {
                $response['errors'] = $errors;
            } else {
                $response['errors'] = ['general' => [$errors]];
            }
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Get error title based on status code.
     */
    protected function getErrorTitle(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad request',
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Not found',
            409 => 'Conflict',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Server error',
            503 => 'Service unavailable',
            default => 'Error',
        };
    }

    /**
     * Return a paginated JSON response.
     */
    protected function paginated($data, string $message = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response);
    }
}

