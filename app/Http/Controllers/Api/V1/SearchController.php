<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Search', description: 'Cross-entity autocomplete suggestions for search bars.')]
class SearchController extends BaseApiController
{
    /**
     * Autocomplete suggestions.
     */
    #[OA\Get(
        path: '/search/suggestions',
        summary: 'Search autocomplete suggestions',
        description: 'Lightweight prefix/substring matching across products (name/SKU, visibility-scoped), categories and manufacturers. Returns up to `limit` rows per entity (max 10). Products additionally carry pharmacist price and primary image when loaded.',
        tags: ['Search'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2), description: 'Search term'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 5, minimum: 1, maximum: 10)),
            new OA\Parameter(name: 'types', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Comma list to restrict entities, e.g. "products,categories"'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Suggestions retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'types' => ['nullable', 'string'],
        ]);

        $user = $this->currentUser();
        $term = str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->q));
        $limit = min(max($request->integer('limit', 5), 1), 10);
        $types = collect(explode(',', (string) $request->input('types', '')))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values();

        $wants = fn (string $type): bool => $types->isEmpty() || $types->contains($type);

        $products = [];
        if ($wants('products')) {
            $products = Product::query()
                ->visibleTo($user)
                ->with('images')
                ->where(function (Builder $q) use ($term): void {
                    $q->whereLike('name', "%{$term}%")
                        ->orWhereLike('sku', "%{$term}%");
                })
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'sku', 'pharmacist_price'])
                ->map(fn (Product $p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price' => $p->pharmacist_price !== null ? (float) $p->pharmacist_price : null,
                    'imageUrl' => $p->images->firstWhere('is_primary', true)?->url ?? $p->images->first()?->url,
                ])
                ->all();
        }

        $categories = [];
        if ($wants('categories')) {
            $categories = Category::query()
                ->whereLike('name', "%{$term}%")
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name'])
                ->all();
        }

        $manufacturers = [];
        if ($wants('manufacturers')) {
            $manufacturers = Manufacturer::query()
                ->whereLike('name', "%{$term}%")
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name'])
                ->all();
        }

        return $this->success([
            'products' => $products,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
        ], 'Suggestions retrieved successfully');
    }
}
