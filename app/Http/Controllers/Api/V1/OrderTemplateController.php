<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Orders\OrderTemplateService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\OrderTemplates\ReorderTemplateRequest;
use App\Http\Requests\OrderTemplates\StoreOrderTemplateRequest;
use App\Http\Requests\OrderTemplates\UpdateOrderTemplateRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderTemplateResource;
use App\Models\OrderTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Order Templates', description: 'Reusable order templates per buyer company. Templates are shared between the company\'s users; reorder places fresh orders at current server-resolved prices.')]
class OrderTemplateController extends BaseApiController
{
    public function __construct(
        private OrderTemplateService $templateService
    ) {}

    /**
     * List my company's templates.
     */
    #[OA\Get(
        path: '/order-templates',
        summary: 'List order templates',
        description: "All templates of the authenticated user's buyer company (shared across company users).",
        tags: ['Order Templates'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Templates retrieved successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('viewAny', OrderTemplate::class);

        $query = OrderTemplate::query()
            ->with(['items.product', 'creator'])
            ->withCount('items')
            ->where('buyer_company_id', (int) $user->company_id)
            ->latest();

        [$templates, $pagination] = $this->paginateOrAll($query, $request);

        $additional = ['success' => true, 'message' => 'Order templates retrieved successfully'];
        if ($pagination !== null) {
            $additional['pagination'] = $pagination;
        }

        return OrderTemplateResource::collection($templates)->additional($additional)->response();
    }

    /**
     * Create a template.
     */
    #[OA\Post(
        path: '/order-templates',
        summary: 'Create order template',
        tags: ['Order Templates'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'items'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Monthly restock'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object', required: ['product_id', 'quantity'], properties: [
                        new OA\Property(property: 'product_id', type: 'integer'),
                        new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
                    ])),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Template created successfully'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function store(StoreOrderTemplateRequest $request): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('create', OrderTemplate::class);

        $template = $this->templateService->create(
            user: $user,
            buyerCompanyId: (int) $user->company_id,
            name: (string) $request->name,
            items: $request->items,
            notes: $request->notes,
        );

        return $this->success(
            new OrderTemplateResource($template->load(['items.product', 'creator'])),
            'Order template created successfully',
            201
        );
    }

    /**
     * Get a template.
     */
    #[OA\Get(
        path: '/order-templates/{id}',
        summary: 'Get order template',
        tags: ['Order Templates'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Template retrieved successfully'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(OrderTemplate $orderTemplate): JsonResponse
    {
        $this->authorize('view', $orderTemplate);

        return $this->success(
            new OrderTemplateResource($orderTemplate->load(['items.product', 'creator'])),
            'Order template retrieved successfully'
        );
    }

    /**
     * Update a template.
     */
    #[OA\Put(
        path: '/order-templates/{id}',
        summary: 'Update order template',
        tags: ['Order Templates'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Template updated successfully'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function update(UpdateOrderTemplateRequest $request, OrderTemplate $orderTemplate): JsonResponse
    {
        $this->authorize('update', $orderTemplate);

        $template = $this->templateService->update(
            template: $orderTemplate,
            name: $request->filled('name') ? (string) $request->name : null,
            notes: $request->has('notes') ? $request->notes : null,
            items: $request->items ?? [],
        );

        return $this->success(
            new OrderTemplateResource($template->load(['items.product', 'creator'])),
            'Order template updated successfully'
        );
    }

    /**
     * Delete a template.
     */
    #[OA\Delete(
        path: '/order-templates/{id}',
        summary: 'Delete order template',
        tags: ['Order Templates'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Template deleted successfully'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(OrderTemplate $orderTemplate): JsonResponse
    {
        $this->authorize('delete', $orderTemplate);

        $this->templateService->delete($orderTemplate);

        return $this->success(null, 'Order template deleted successfully');
    }

    /**
     * Reorder from a template.
     */
    #[OA\Post(
        path: '/order-templates/{id}/reorder',
        summary: 'Reorder from template',
        description: 'Places fresh orders from the template — one order per supplier, grouped automatically — at current server-resolved prices. Credit rules and catalog visibility apply.',
        tags: ['Order Templates'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'net_15', 'net_30', 'net_60'], nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Orders placed. Returns an array of created orders.'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/UnprocessableEntity'),
        ]
    )]
    public function reorder(ReorderTemplateRequest $request, OrderTemplate $orderTemplate): JsonResponse
    {
        $user = $this->currentUser();
        $this->authorize('reorder', $orderTemplate);

        $orders = $this->templateService->reorder(
            template: $orderTemplate,
            buyerUser: $user,
            paymentMethod: $request->payment_method,
        );

        return $this->success(
            OrderResource::collection(collect($orders))->resolve(),
            count($orders).' order(s) placed from template',
        );
    }
}
