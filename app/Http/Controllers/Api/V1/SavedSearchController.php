<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Search\SavedSearchService;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Saved Searches', description: 'Per-user saved search filters and recent search history. Saved params can be replayed against the matching list endpoint via ?saved_search_id=.')]
class SavedSearchController extends BaseApiController
{
    public function __construct(
        private SavedSearchService $savedSearchService
    ) {}

    /**
     * List my saved searches.
     */
    #[OA\Get(
        path: '/saved-searches',
        summary: 'List saved searches',
        tags: ['Saved Searches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'entity', in: 'query', schema: new OA\Schema(type: 'string', enum: ['products', 'companies', 'orders']))],
        responses: [
            new OA\Response(response: 200, description: 'Saved searches retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $searches = $this->savedSearchService->listFor($this->currentUser(), $request->input('entity'));

        return $this->success(
            $searches->map(fn ($s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'entity' => $s->entity,
                'params' => $s->params,
                'createdAt' => $s->created_at?->toISOString(),
            ]),
            'Saved searches retrieved successfully'
        );
    }

    /**
     * Save a search.
     */
    #[OA\Post(
        path: '/saved-searches',
        summary: 'Save a search filter',
        description: 'Store the current filter combination for later replay. `params` must be the query-string map of the target list endpoint (e.g. category_ids, min_price, sort).',
        tags: ['Saved Searches'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'entity'], properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Cheap vitamins in stock'),
            new OA\Property(property: 'entity', type: 'string', enum: ['products', 'companies', 'orders']),
            new OA\Property(property: 'params', type: 'object', description: 'Query-string map of the list endpoint filters'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Search saved successfully'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'entity' => ['required', 'string', Rule::in(['products', 'companies', 'orders'])],
            'params' => ['required', 'array'],
            'params.*' => [],
        ]);

        $search = $this->savedSearchService->create(
            user: $this->currentUser(),
            name: (string) $data['name'],
            entity: (string) $data['entity'],
            params: $data['params'],
        );

        return $this->success([
            'id' => $search->id,
            'name' => $search->name,
            'entity' => $search->entity,
            'params' => $search->params,
        ], 'Search saved successfully', 201);
    }

    /**
     * Delete a saved search.
     */
    #[OA\Delete(
        path: '/saved-searches/{id}',
        summary: 'Delete saved search',
        tags: ['Saved Searches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(SavedSearch $savedSearch): JsonResponse
    {
        if ((int) $savedSearch->user_id !== (int) $this->currentUser()->id) {
            return $this->error('You do not own this saved search', null, 403);
        }

        $savedSearch->delete();

        return $this->success(null, 'Saved search deleted successfully');
    }

    /**
     * Record a search term.
     */
    #[OA\Post(
        path: '/search/history',
        summary: 'Record a search term',
        description: 'Adds the term to the user\'s search history (kept to the 20 most recent entries; consecutive duplicates collapsed).',
        tags: ['Saved Searches'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['entity', 'term'], properties: [
            new OA\Property(property: 'entity', type: 'string', enum: ['products', 'companies', 'orders']),
            new OA\Property(property: 'term', type: 'string', maxLength: 100),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Recorded'),
        ]
    )]
    public function recordHistory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'string', Rule::in(['products', 'companies', 'orders'])],
            'term' => ['required', 'string', 'max:100'],
        ]);

        $this->savedSearchService->recordHistory(
            user: $this->currentUser(),
            entity: (string) $data['entity'],
            term: (string) $data['term'],
        );

        return $this->success(null, 'Search recorded');
    }

    /**
     * My recent searches.
     */
    #[OA\Get(
        path: '/search/history',
        summary: 'Recent search terms',
        description: 'The authenticated user\'s most recent search terms (max 20), newest first.',
        tags: ['Saved Searches'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'entity', in: 'query', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'History retrieved'),
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $history = $this->savedSearchService->historyFor($this->currentUser(), $request->input('entity'));

        return $this->success(
            $history->map(fn ($h): array => [
                'id' => $h->id,
                'entity' => $h->entity,
                'term' => $h->term,
                'searchedAt' => $h->created_at?->toISOString(),
            ]),
            'Search history retrieved successfully'
        );
    }
}
